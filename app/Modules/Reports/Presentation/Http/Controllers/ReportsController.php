<?php

namespace App\Modules\Reports\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use App\Models\CouponUsage;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\OrderReturn;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\PaymentOperation;
use App\Models\Shipment;
use App\Models\ShipmentOperation;
use App\Models\CarrierSettlement;
use Illuminate\Support\Facades\DB;
use App\Modules\Reports\Presentation\Http\Requests\ReportRequest;
use Illuminate\Http\JsonResponse;

final class ReportsController extends Controller
{
    public function sales(ReportRequest $request): JsonResponse
    {
        $orders = CustomerOrder::query()->whereBetween('created_at', $this->range($request))->get();
        $byStatus = $orders->groupBy('status')->map->count();
        return response()->json(['data' => [
            'from' => $request->validated('from'), 'to' => $request->validated('to'),
            'orders' => $orders->count(), 'by_status' => $byStatus,
            'gross_sales' => $orders->sum('subtotal_amount'), 'discounts' => $orders->sum('discount_amount'),
            'taxes' => $orders->sum('tax_amount'), 'shipping' => $orders->sum('shipping_amount'),
            'net_sales' => $orders->whereNotIn('status', ['cancelled', 'refunded'])->sum('total_amount'),
            'average_order_value' => $orders->count() ? (int) round($orders->avg('total_amount')) : 0,
            'currency' => $orders->pluck('currency')->filter()->unique()->values(),
        ]]);
    }

    public function payments(ReportRequest $request): JsonResponse
    {
        $payments = Payment::query()->whereBetween('created_at', $this->range($request))
            ->when($request->validated('method'), fn ($query, $method) => $query->where('method', $method))->get();
        return response()->json(['data' => [
            'from' => $request->validated('from'), 'to' => $request->validated('to'),
            'count' => $payments->count(), 'by_status' => $payments->groupBy('status')->map(fn ($items) => ['count' => $items->count(), 'amount' => $items->sum('amount')]),
            'by_method' => $payments->groupBy('method')->map(fn ($items) => ['count' => $items->count(), 'amount' => $items->sum('amount')]),
            'reconciliation_required' => $payments->filter(fn ($payment) => (bool) data_get($payment->metadata, 'reconciliation_required'))->values(),
        ]]);
    }

    public function returns(ReportRequest $request): JsonResponse
    {
        $returns = OrderReturn::query()->with('items')->whereBetween('created_at', $this->range($request))->get();
        return response()->json(['data' => [
            'from' => $request->validated('from'), 'to' => $request->validated('to'),
            'count' => $returns->count(), 'by_status' => $returns->groupBy('status')->map(fn ($items) => ['count' => $items->count(), 'refund_amount' => $items->sum('refund_amount')]),
            'refund_amount' => $returns->sum('refund_amount'),
            'top_reasons' => $returns->groupBy('reason')->map->count()->sortDesc()->take(10),
            'items' => $returns->flatMap->items->groupBy('product_id')->map(fn ($items) => ['quantity' => $items->sum('quantity'), 'amount' => $items->sum(fn ($item) => $item->quantity * $item->unit_price)])->sortByDesc('quantity')->take(10),
        ]]);
    }

    public function carrierPerformance(ReportRequest $request): JsonResponse
    {
        $shipments = Shipment::query()->with(['method', 'events'])->whereBetween('created_at', $this->range($request))
            ->when($request->validated('carrier'), fn ($query, $carrier) => $query->whereHas('method', fn ($method) => $method->where('carrier', $carrier)))->get();
        $rows = $shipments->groupBy(fn ($shipment) => (string) ($shipment->method?->carrier ?: $shipment->method_code))->map(function ($items, $carrier): array {
            $durations = $items->map(function ($shipment) {
                $created = $shipment->events->firstWhere('to_status', 'picked_up')?->created_at;
                $delivered = $shipment->events->firstWhere('to_status', 'delivered')?->created_at;
                return $created && $delivered ? $created->diffInHours($delivered) : null;
            })->filter();
            $total = max($items->count(), 1);
            return ['carrier' => $carrier, 'shipments' => $items->count(), 'delivered' => $items->where('status', 'delivered')->count(),
                'cancelled' => $items->where('status', 'cancelled')->count(), 'failed' => $items->where('status', 'failed')->count(),
                'delivery_rate' => round($items->where('status', 'delivered')->count() * 100 / $total, 2),
                'return_or_cancel_rate' => round($items->whereIn('status', ['cancelled'])->count() * 100 / $total, 2),
                'average_delivery_hours' => $durations->count() ? round($durations->avg(), 2) : null,
                'open_shipments' => $items->whereNotIn('status', ['delivered', 'cancelled', 'failed'])->count(),
            ];
        })->values();
        return response()->json(['data' => ['from' => $request->validated('from'), 'to' => $request->validated('to'), 'carriers' => $rows]]);
    }

    public function inventory(ReportRequest $request): JsonResponse
    {
        $threshold = (int) ($request->validated('threshold') ?? 5);
        $items = InventoryItem::query()->with(['product:id,name', 'variant:id,sku'])->get();
        return response()->json(['data' => [
            'items' => $items->map(fn ($item) => ['product_id' => $item->product_id, 'product' => $item->product?->name,
                'sku' => $item->variant?->sku, 'variant_id' => $item->variant_id,
                'on_hand' => $item->on_hand, 'reserved' => $item->reserved, 'available' => $item->available,
                'low_stock' => $item->available <= $threshold])->values(),
            'totals' => ['items' => $items->count(), 'on_hand' => $items->sum('on_hand'), 'reserved' => $items->sum('reserved'),
                'available' => $items->sum(fn ($item) => $item->available), 'low_stock' => $items->filter(fn ($item) => $item->available <= $threshold)->count()],
            'threshold' => $threshold,
            'movement_summary' => InventoryMovement::query()->whereBetween('created_at', $this->range($request))->groupBy('reason')->selectRaw('reason, COUNT(*) as count, SUM(quantity) as quantity')->get(),
        ]]);
    }

    public function customers(ReportRequest $request): JsonResponse
    {
        $orders = CustomerOrder::query()->with('user:id,name,email')->whereBetween('created_at', $this->range($request))->whereNotNull('user_id')->get();
        $customers = $orders->groupBy('user_id')->map(function ($items, $userId): array {
            $user = $items->first()->user;
            return ['user_id' => (int) $userId, 'name' => $user?->name, 'email' => $user?->email,
                'orders' => $items->count(), 'total_spend' => $items->whereNotIn('status', ['cancelled', 'refunded'])->sum('total_amount'),
                'average_order_value' => $items->count() ? (int) round($items->avg('total_amount')) : 0];
        })->sortByDesc('total_spend')->values();
        return response()->json(['data' => ['from' => $request->validated('from'), 'to' => $request->validated('to'), 'customers' => $customers, 'customer_count' => $customers->count()]]);
    }

    public function products(ReportRequest $request): JsonResponse
    {
        $items = CustomerOrder::query()->with('items.product:id,name')->whereBetween('created_at', $this->range($request))->whereNotIn('status', ['cancelled', 'refunded'])->get()->flatMap->items;
        $products = $items->groupBy('product_id')->map(function ($rows, $productId): array {
            $product = $rows->first()->product;
            return ['product_id' => (int) $productId, 'name' => $product?->name, 'quantity' => $rows->sum('quantity'), 'revenue' => $rows->sum('total_amount'), 'orders' => $rows->pluck('order_id')->unique()->count()];
        })->sortByDesc('revenue')->values();
        return response()->json(['data' => ['from' => $request->validated('from'), 'to' => $request->validated('to'), 'products' => $products]]);
    }

    public function coupons(ReportRequest $request): JsonResponse
    {
        $usages = CouponUsage::query()->with('coupon:id,code,type,value')->whereBetween('created_at', $this->range($request))->get();
        $coupons = $usages->groupBy('coupon_id')->map(function ($rows, $couponId): array {
            return ['coupon_id' => (int) $couponId, 'code' => $rows->first()->coupon?->code, 'uses' => $rows->count(), 'discount_amount' => $rows->sum('discount_amount'), 'orders' => $rows->pluck('order_id')->filter()->unique()->count()];
        })->sortByDesc('discount_amount')->values();
        return response()->json(['data' => ['from' => $request->validated('from'), 'to' => $request->validated('to'), 'coupons' => $coupons, 'total_discount_amount' => $usages->sum('discount_amount')]]);
    }

    public function taxes(ReportRequest $request): JsonResponse
    {
        $orders = CustomerOrder::query()->whereBetween('created_at', $this->range($request))->whereNotIn('status', ['cancelled', 'refunded'])->get();
        $rows = $orders->groupBy(fn ($order) => (string) ($order->tax_rule_id ?: 'rate:'.$order->tax_rate))->map(fn ($items, $key) => [
            'tax_rule' => $key, 'rate' => (string) $items->first()->tax_rate, 'orders' => $items->count(), 'taxable_sales' => $items->sum('subtotal_amount'), 'tax_amount' => $items->sum('tax_amount'),
        ])->values();
        return response()->json(['data' => ['from' => $request->validated('from'), 'to' => $request->validated('to'), 'rows' => $rows, 'total_taxable_sales' => $orders->sum('subtotal_amount'), 'total_tax_amount' => $orders->sum('tax_amount')]]);
    }

    public function cashflow(ReportRequest $request): JsonResponse
    {
        $payments = Payment::query()->whereBetween('created_at', $this->range($request))->get();
        $settlements = CarrierSettlement::query()->whereBetween('created_at', $this->range($request))->get();
        $received = $payments->whereIn('status', ['confirmed', 'paid'])->sum('amount');
        $refunded = $payments->where('status', 'refunded')->sum('amount');
        $carrierPaid = $settlements->sum('paid_amount');
        return response()->json(['data' => ['from' => $request->validated('from'), 'to' => $request->validated('to'),
            'payment_received' => $received, 'payment_refunded' => $refunded, 'carrier_settlements_paid' => $carrierPaid,
            'net_cash_movement' => $received + $carrierPaid - $refunded, 'pending_payment_amount' => $payments->whereIn('status', ['pending', 'processing', 'provider_created'])->sum('amount'),
            'note' => 'Gateway fees and operating expenses are not available until fee fields are added to payment settlements.',
        ]]);
    }

    public function paymentExceptions(ReportRequest $request): JsonResponse
    {
        $payments = Payment::query()->whereBetween('created_at', $this->range($request))->whereIn('status', ['pending', 'processing', 'provider_created', 'failed', 'abandoned'])->with('order:id,status')->get();
        return response()->json(['data' => ['count' => $payments->count(), 'amount' => $payments->sum('amount'), 'by_status' => $payments->groupBy('status')->map(fn ($items) => ['count' => $items->count(), 'amount' => $items->sum('amount')]), 'items' => $payments->values()]]);
    }

    public function operations(ReportRequest $request): JsonResponse
    {
        $range = $this->range($request);
        $outbox = OutboxEvent::query()->whereBetween('created_at', $range)->get();
        $paymentOperations = PaymentOperation::query()->whereBetween('created_at', $range)->get();
        $shipmentOperations = ShipmentOperation::query()->whereBetween('created_at', $range)->get();
        return response()->json(['data' => ['outbox' => ['total' => $outbox->count(), 'by_status' => $outbox->groupBy('status')->map->count(), 'dead_lettered' => $outbox->where('status', 'dead_lettered')->count()],
            'payment_operations' => ['total' => $paymentOperations->count(), 'by_status' => $paymentOperations->groupBy('status')->map->count(), 'failed' => $paymentOperations->where('status', 'failed')->count()],
            'shipment_operations' => ['total' => $shipmentOperations->count(), 'by_status' => $shipmentOperations->groupBy('status')->map->count(), 'failed' => $shipmentOperations->where('status', 'failed')->count()],
            'failed_jobs' => DB::table('failed_jobs')->whereBetween('failed_at', $range)->count(),
        ]]);
    }

    private function range(ReportRequest $request): array
    {
        return [$request->validated('from').' 00:00:00', $request->validated('to').' 23:59:59'];
    }
}
