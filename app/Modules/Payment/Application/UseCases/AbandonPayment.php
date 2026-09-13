<?php

namespace App\Modules\Payment\Application\UseCases;

use App\Modules\Order\Domain\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Domain\Contracts\TransactionManagerInterface;
use App\Modules\Payment\Domain\Contracts\PaymentRepositoryInterface;

final class AbandonPayment
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly OrderRepositoryInterface $orders,
        private readonly TransactionManagerInterface $transactions,
    ) {}

    public function execute(int $paymentId): object
    {
        return $this->transactions->run(function () use ($paymentId): object {
            $payment = $this->payments->findForUpdate($paymentId);
            if ((string) $payment->status !== 'processing') {
                return $payment;
            }

            $abandoned = $this->payments->updateStatus($payment, 'abandoned', [
                'metadata' => array_merge((array) $payment->metadata, [
                    'abandoned_at' => now()->toIso8601String(),
                    'reconciliation_required' => true,
                ]),
            ]);

            // Order cancellation releases every inventory reservation atomically.
            $this->orders->cancel((int) $abandoned->order_id);

            return $abandoned->fresh(['order']);
        });
    }
}
