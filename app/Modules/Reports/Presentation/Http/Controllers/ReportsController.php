<?php

namespace App\Modules\Reports\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Shipment;
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

    private function range(ReportRequest $request): array
    {
        return [$request->validated('from').' 00:00:00', $request->validated('to').' 23:59:59'];
    }
}
