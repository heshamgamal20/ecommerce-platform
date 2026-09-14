<?php
namespace App\Modules\Order\Infrastructure\Persistence;
use App\Models\CreditNote;
use App\Models\CustomerOrder;
use App\Models\Invoice;
use App\Modules\Order\Domain\Contracts\InvoiceRepositoryInterface;
use App\Modules\Order\Domain\Exceptions\InvoiceException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
final class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function get(int $orderId): object
    {
        $invoice = Invoice::query()->with(['items','creditNotes'])->where('order_id', $orderId)->first();
        if ($invoice === null) throw new InvoiceException('Invoice not found.');
        return $invoice;
    }
    public function issue(int $orderId): object
    {
        return DB::transaction(function () use ($orderId): object {
            $order = CustomerOrder::query()->with('items')->find($orderId);
            if ($order === null) throw new InvoiceException('Order not found.');
            $existing = Invoice::query()->where('order_id', $orderId)->first();
            if ($existing !== null) return $existing->load('items');
            $invoice = Invoice::query()->create(['order_id'=>$order->id,'number'=>'INV-'.now()->format('Ym').'-'.Str::upper(Str::random(8)),'status'=>'issued','type'=>'tax','currency'=>$order->currency,'subtotal_amount'=>$order->subtotal_amount,'tax_amount'=>$order->tax_amount,'total_amount'=>$order->total_amount,'issued_at'=>now()]);
            foreach ($order->items as $item) $invoice->items()->create(['order_item_id'=>$item->id,'name'=>$item->name,'quantity'=>$item->quantity,'unit_price'=>$item->unit_price,'tax_amount'=>$item->tax_amount ?? 0,'total_amount'=>$item->total_amount ?? ($item->unit_price * $item->quantity)]);
            return $invoice->load('items');
        });
    }
    public function cancel(int $invoiceId): object
    {
        $invoice = Invoice::query()->find($invoiceId);
        if ($invoice === null) throw new InvoiceException('Invoice not found.');
        if ($invoice->status === 'cancelled') return $invoice;
        if ($invoice->creditNotes()->exists()) throw new InvoiceException('Invoice with credit notes cannot be cancelled.');
        $invoice->update(['status'=>'cancelled','cancelled_at'=>now()]);
        return $invoice->fresh('items');
    }
    public function issueCreditNote(int $invoiceId, ?int $returnId, int $amount, ?string $reason): object
    {
        return DB::transaction(function () use ($invoiceId,$returnId,$amount,$reason): object {
            $invoice = Invoice::query()->with('creditNotes')->find($invoiceId);
            if ($invoice === null) throw new InvoiceException('Invoice not found.');
            $credited = (int) $invoice->creditNotes->where('status','issued')->sum('amount');
            if ($amount < 1 || $credited + $amount > $invoice->total_amount) throw new InvoiceException('Credit note amount exceeds invoice balance.');
            return CreditNote::query()->create(['invoice_id'=>$invoice->id,'return_id'=>$returnId,'number'=>'CN-'.now()->format('Ym').'-'.Str::upper(Str::random(8)),'status'=>'issued','reason'=>$reason,'currency'=>$invoice->currency,'amount'=>$amount,'issued_at'=>now()]);
        });
    }
}
