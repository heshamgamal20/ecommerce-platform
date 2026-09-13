<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\CustomerOrder;
use App\Modules\Order\Domain\Contracts\OrderRepositoryInterface;
use App\Modules\Payment\Application\UseCases\ReconcilePayment;
use App\Modules\Payment\Application\UseCases\AbandonPayment;
use Illuminate\Console\Command;

if (! class_exists(__NAMESPACE__ . '\\ReconcileStalePayments', false)) {
final class ReconcileStalePayments extends Command
{
    protected $signature = 'payments:reconcile {--minutes=5 : Minimum age of a non-terminal payment}';
    protected $description = 'Reconcile stale processing and provider-created payments with their provider';

    public function handle(): int
    {
        $count = 0;
        $expiredOrders = 0;
        $timeout = (int) config('payment.processing_timeout_minutes', 60);
        Payment::query()->where('status', 'processing')
            ->where('updated_at', '<=', now()->subMinutes($timeout))
            ->orderBy('id')->limit(100)->pluck('id')->each(function (int $paymentId) use (&$count): void {
                try {
                    app(AbandonPayment::class)->execute($paymentId);
                    $count++;
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        Payment::query()->where('status', 'provider_created')
            ->where('updated_at', '<=', now()->subMinutes((int) $this->option('minutes')))
            ->orderBy('id')->limit(100)->pluck('id')->each(function (int $paymentId) use (&$count): void {
                try {
                    app(ReconcilePayment::class)->execute($paymentId);
                    $count++;
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        CustomerOrder::query()->with('payments')->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes((int) config('order.pending_reservation_timeout_minutes', 60)))
            ->orderBy('id')->limit(100)->get()->each(function (CustomerOrder $order) use (&$expiredOrders): void {
                // COD is an intentional pending business flow; only orders with
                // no payment or a non-COD, non-terminal payment may expire.
                $payments = $order->payments;
                if ($payments->contains(fn (Payment $payment): bool => $payment->method === 'cash_on_delivery')) {
                    return;
                }
                if ($payments->contains(fn (Payment $payment): bool => in_array($payment->status, ['confirmed', 'paid', 'refunded'], true))) {
                    return;
                }
                try {
                    app(OrderRepositoryInterface::class)->cancel((int) $order->id);
                    $expiredOrders++;
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        $this->info("Reconciled {$count} payment(s) and expired {$expiredOrders} order reservation(s).");
        return self::SUCCESS;
    }
}
}
