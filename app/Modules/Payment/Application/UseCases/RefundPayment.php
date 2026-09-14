<?php

namespace App\Modules\Payment\Application\UseCases;

use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\OrderReturn;
use App\Models\OutboxEvent;
use App\Modules\Order\Domain\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Domain\Contracts\TransactionManagerInterface;
use App\Modules\Payment\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Domain\Contracts\PaymentOperationRepositoryInterface;
use App\Modules\Payment\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Payment\Domain\Exceptions\InvalidPaymentTransitionException;
use App\Modules\Payment\Domain\Exceptions\PaymentFailedException;
use Illuminate\Support\Str;

final class RefundPayment
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly PaymentOperationRepositoryInterface $operations,
        private readonly PaymentGatewayInterface $gateway,
        private readonly OrderRepositoryInterface $orders,
        private readonly TransactionManagerInterface $transactions,
    ) {}

    public function execute(int $paymentId): object
    {
        $payment = $this->payments->find($paymentId);
        $return = OrderReturn::query()->where('payment_id', $payment->id)->first();

        if ((string) $payment->status === 'refunded') {
            if ($return !== null && $return->refunded_at === null) {
                $return->update(['status' => 'refunded', 'refunded_at' => now()]);
            }

            return $payment;
        }

        $refundAmount = $this->refundAmount($payment, $return);
        if ($refundAmount < 1) {
            throw new PaymentFailedException('Payment has no refundable balance.');
        }
        if (! in_array($payment->status, ['paid', 'confirmed'], true)) {
            throw InvalidPaymentTransitionException::from($payment->status, 'refunded');
        }

        if ($return !== null) {
            if ($return->status !== 'approved'
                || $return->received_at === null
                || ! in_array($return->inspection_status, ['passed', 'partial'], true)
                || $return->final_refund_amount === null
                || $return->refunded_at !== null) {
                throw new PaymentFailedException('Return is not ready for refund.');
            }
            $creditNote = CreditNote::query()->where('return_id', $return->id)->where('status', 'issued')->first();
            if ($creditNote === null || (int) $creditNote->amount !== (int) $refundAmount) {
                throw new PaymentFailedException('Return must have a matching credit note before refund.');
            }
        }

        $operation = $return !== null ? 'refund_return_'.$return->id : 'refund_full';
        $operationKey = $operation.':'.$payment->id;
        $previous = $this->operations->successfulResponse((int) $payment->id, $operation);
        if ($previous !== null) {
            return $this->finalizeRefund($paymentId, $payment, $return, $refundAmount, $previous);
        }

        $this->operations->start((int) $payment->id, $operation, $operationKey);
        $leaseToken = (string) Str::uuid();
        if (! $this->operations->acquireLease((int) $payment->id, $operation, $leaseToken)) {
            throw new PaymentFailedException('Refund operation is already in progress.');
        }

        try {
            $result = $this->gateway->refundPayment($payment, $refundAmount);
            if (($result['status'] ?? null) !== 'refunded') {
                throw new PaymentFailedException('Payment refund failed.');
            }
            $this->operations->complete((int) $payment->id, $operation, 'confirmed', $payment->provider_reference, $result);
            OutboxEvent::query()->firstOrCreate(
                ['deduplication_key' => 'payment:'.$operation.':'.$payment->id],
                ['aggregate_type' => 'payment', 'aggregate_id' => $payment->id, 'event_type' => 'payment.refund.completed', 'status' => 'pending', 'payload' => ['payment_id' => $payment->id, 'amount' => $refundAmount, 'provider_reference' => $payment->provider_reference]],
            );
        } catch (\Throwable $exception) {
            $this->operations->fail((int) $payment->id, $operation, $exception->getMessage(), ! ($exception instanceof PaymentFailedException));
            throw $exception;
        }

        return $this->finalizeRefund($paymentId, $payment, $return, $refundAmount, $result);
    }

    private function refundAmount(object $payment, ?OrderReturn $return): int
    {
        if ($return !== null) {
            return (int) ($return->final_refund_amount ?? 0);
        }

        return max(0, (int) $payment->amount - (int) ($payment->refunded_amount ?? 0));
    }

    private function finalizeRefund(int $paymentId, object $payment, ?OrderReturn $return, int $refundAmount, array $result): object
    {
        return $this->transactions->run(function () use ($paymentId, $payment, $return, $refundAmount, $result): object {
            $locked = $this->payments->findForUpdate($paymentId);
            $alreadyRefunded = (int) ($locked->refunded_amount ?? 0);
            if ($alreadyRefunded >= $refundAmount) {
                if ($return !== null && $return->refunded_at === null) {
                    $return->update(['status' => 'refunded', 'refunded_at' => now()]);
                }
                return $locked;
            }
            if ($alreadyRefunded + $refundAmount > (int) $locked->amount) {
                throw new PaymentFailedException('Refund exceeds the payment balance.');
            }

            $newRefundedAmount = $alreadyRefunded + $refundAmount;
            $newStatus = $newRefundedAmount === (int) $locked->amount ? 'refunded' : $locked->status;
            $refunded = $this->payments->updateStatus($locked, $newStatus, [
                'refunded_amount' => $newRefundedAmount,
                'metadata' => $result['metadata'] ?? $locked->metadata,
            ]);

            if ($newStatus === 'refunded') {
                $this->orders->markRefunded($payment->order_id);
            }
            if ($return !== null) {
                $return->update(['status' => 'refunded', 'refunded_at' => now()]);
            }
            AuditLog::query()->create([
                'actor_id' => auth()->id(),
                'action' => 'payment.refunded',
                'target_type' => get_class($refunded),
                'target_id' => $refunded->id,
                'metadata' => ['amount' => $refundAmount, 'provider_reference' => $refunded->provider_reference],
            ]);

            return $refunded;
        });
    }
}
