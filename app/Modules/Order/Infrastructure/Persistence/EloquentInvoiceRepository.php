<?php

namespace App\Modules\Order\Infrastructure\Persistence;

use App\Models\CreditNote;
use App\Models\CustomerOrder;
use App\Models\Invoice;
use App\Models\OrderReturn;
use App\Modules\Order\Domain\Contracts\InvoiceRepositoryInterface;
use App\Modules\Order\Domain\Exceptions\InvoiceException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function get(int $orderId): object
    {
        $invoice = Invoice::query()->with(['items', 'creditNotes'])->where('order_id', $orderId)->first();
        if ($invoice === null) {
            throw new InvoiceException('Invoice not found.');
        }

        return $invoice;
    }

    public function issue(int $orderId): object
    {
        return DB::transaction(function () use ($orderId): object {
            $order = CustomerOrder::query()->with('items')->find($orderId);
            if ($order === null) {
                throw new InvoiceException('Order not found.');
            }

            $existing = Invoice::query()->where('order_id', $orderId)->first();
            if ($existing !== null) {
                return $existing->load('items');
            }

            $invoice = Invoice::query()->create([
                'order_id' => $order->id,
                'number' => 'INV-'.now()->format('Ym').'-'.Str::upper(Str::random(8)),
                'status' => 'issued',
                'type' => 'tax',
                'currency' => $order->currency,
                'subtotal_amount' => $order->subtotal_amount,
                'tax_amount' => $order->tax_amount,
                'total_amount' => $order->total_amount,
                'issued_at' => now(),
            ]);
            foreach ($order->items as $item) {
                $invoice->items()->create([
                    'order_item_id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_amount' => $item->tax_amount ?? 0,
                    'total_amount' => $item->total_amount ?? ($item->unit_price * $item->quantity),
                ]);
            }

            return $invoice->load('items');
        });
    }

    public function cancel(int $invoiceId): object
    {
        return DB::transaction(function () use ($invoiceId): object {
            $invoice = Invoice::query()->lockForUpdate()->find($invoiceId);
            if ($invoice === null) {
                throw new InvoiceException('Invoice not found.');
            }
            if ($invoice->status === 'cancelled') {
                return $invoice;
            }
            if ($invoice->status !== 'issued') {
                throw new InvoiceException('Only issued invoices can be cancelled.');
            }
            if ($invoice->creditNotes()->exists()) {
                throw new InvoiceException('Invoice with credit notes cannot be cancelled.');
            }

            $order = $invoice->order()->with(['payments', 'returns'])->first();
            if ($order?->payments->contains(fn ($payment): bool => in_array($payment->status, ['paid', 'confirmed', 'refunded'], true))) {
                throw new InvoiceException('Invoice with a payment cannot be cancelled.');
            }
            if ($order?->returns->contains(fn ($return): bool => $return->status !== 'rejected')) {
                throw new InvoiceException('Invoice with an active return cannot be cancelled.');
            }

            $invoice->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return $invoice->fresh('items');
        });
    }

    public function issueCreditNote(int $invoiceId, ?int $returnId, int $amount, ?string $reason): object
    {
        return DB::transaction(function () use ($invoiceId, $returnId, $amount, $reason): object {
            $invoice = Invoice::query()->lockForUpdate()->with('creditNotes')->find($invoiceId);
            if ($invoice === null) {
                throw new InvoiceException('Invoice not found.');
            }
            if ($invoice->status !== 'issued') {
                throw new InvoiceException('Credit notes can only be issued for an issued invoice.');
            }

            $return = null;
            if ($returnId !== null) {
                $return = OrderReturn::query()->lockForUpdate()->find($returnId);
                if ($return === null || (int) $return->order_id !== (int) $invoice->order_id) {
                    throw new InvoiceException('Return does not belong to the invoice order.');
                }
                if (! in_array($return->status, ['approved'], true) || $return->refunded_at !== null) {
                    throw new InvoiceException('Return is not eligible for a credit note.');
                }
                if (CreditNote::query()->where('return_id', $return->id)->exists()) {
                    throw new InvoiceException('Return has already been used for a credit note.');
                }

                $expectedAmount = (int) ($return->final_refund_amount ?? $return->refund_amount);
                if ($expectedAmount < 1 || $amount !== $expectedAmount) {
                    throw new InvoiceException('Credit note amount must match the return refund amount.');
                }
            }

            $credited = (int) $invoice->creditNotes->where('status', 'issued')->sum('amount');
            if ($amount < 1 || $credited + $amount > (int) $invoice->total_amount) {
                throw new InvoiceException('Credit note amount exceeds invoice balance.');
            }

            return CreditNote::query()->create([
                'invoice_id' => $invoice->id,
                'return_id' => $return?->id,
                'number' => 'CN-'.now()->format('Ym').'-'.Str::upper(Str::random(8)),
                'status' => 'issued',
                'reason' => $reason,
                'currency' => $invoice->currency,
                'amount' => $amount,
                'issued_at' => now(),
            ]);
        });
    }
}
