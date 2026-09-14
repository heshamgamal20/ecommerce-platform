<?php

namespace App\Modules\Administration\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Shipment;
use App\Modules\Administration\Presentation\Http\Requests\AdminDashboardRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class AdminDashboardController extends Controller
{
    public function __invoke(AdminDashboardRequest $request): JsonResponse
    {
        $from = ($request->validated('from') ?? now()->toDateString()).' 00:00:00';
        $to = ($request->validated('to') ?? now()->toDateString()).' 23:59:59';
        $threshold = (int) ($request->validated('threshold') ?? 5);
        $orders = CustomerOrder::query()->whereBetween('created_at', [$from, $to])->get();
        $payments = Payment::query()->whereBetween('created_at', [$from, $to])->get();
        $shipments = Shipment::query()->whereBetween('created_at', [$from, $to])->get();
        $returns = OrderReturn::query()->whereBetween('created_at', [$from, $to])->get();
        $inventory = InventoryItem::query()->get();
        $outbox = DB::table('outbox_events')->where('status', 'dead_letter')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $activePaymentStatuses = ['succeeded', 'paid', 'confirmed'];
        $revenueOrders = $orders->whereNotIn('status', ['cancelled', 'refunded']);
        return response()->json(['data' => [
            'generated_at' => now()->toIso8601String(), 'period' => ['from' => substr($from, 0, 10), 'to' => substr($to, 0, 10)],
            'sales' => ['orders' => $revenueOrders->count(), 'gross_amount' => $revenueOrders->sum('total_amount'), 'discount_amount' => $revenueOrders->sum('discount_amount'), 'tax_amount' => $revenueOrders->sum('tax_amount'), 'shipping_amount' => $revenueOrders->sum('shipping_amount'), 'average_order_value' => $revenueOrders->count() ? (int) round($revenueOrders->avg('total_amount')) : 0, 'by_status' => $orders->groupBy('status')->map->count()],
            'payments' => ['count' => $payments->count(), 'collected_amount' => $payments->whereIn('status', $activePaymentStatuses)->sum('amount'), 'pending_amount' => $payments->whereIn('status', ['pending', 'processing', 'provider_created'])->sum('amount'), 'failed_count' => $payments->whereIn('status', ['failed', 'abandoned'])->count(), 'by_status' => $payments->groupBy('status')->map(fn ($items) => ['count' => $items->count(), 'amount' => $items->sum('amount')])],
            'shipping' => ['shipments' => $shipments->count(), 'by_status' => $shipments->groupBy('status')->map->count(), 'open_shipments' => $shipments->whereNotIn('status', ['delivered', 'cancelled', 'failed', 'returned'])->count(), 'failed_or_returned' => $shipments->whereIn('status', ['failed', 'returned'])->count()],
            'returns' => ['count' => $returns->count(), 'refund_amount' => $returns->whereNotIn('status', ['rejected', 'cancelled'])->sum('refund_amount'), 'by_status' => $returns->groupBy('status')->map->count()],
            'inventory' => ['items' => $inventory->count(), 'on_hand' => $inventory->sum('on_hand'), 'reserved' => $inventory->sum('reserved'), 'available' => $inventory->sum(fn ($item) => $item->available), 'low_stock' => $inventory->filter(fn ($item) => $item->available <= $threshold)->count(), 'threshold' => $threshold],
            'alerts' => ['failed_jobs' => $failedJobs, 'outbox_dead_letter' => $outbox, 'unread_admin_notifications' => AdminNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->count(), 'pending_payments' => $payments->whereIn('status', ['pending', 'processing', 'provider_created'])->count(), 'open_shipments' => $shipments->whereNotIn('status', ['delivered', 'cancelled', 'failed', 'returned'])->count()],
        ]]);
    }
}
