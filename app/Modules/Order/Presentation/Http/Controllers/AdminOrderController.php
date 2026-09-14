<?php

namespace App\Modules\Order\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use App\Modules\Order\Presentation\Http\Requests\AdminOrderRequest;
use Illuminate\Http\JsonResponse;

final class AdminOrderController extends Controller
{
    public function index(AdminOrderRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $orders = CustomerOrder::query()->with(['user:id,name,email,phone', 'payments:id,order_id,method,amount,currency,status,provider_reference', 'shipments:id,order_id,method_code,tracking_number,status,fee,currency'])
            ->withCount(['items', 'payments', 'shipments', 'returns'])
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($inner) => $inner->where('id', is_numeric($q) ? (int) $q : 0)->orWhere('guest_email', 'like', '%'.$q.'%')->orWhere('guest_phone', 'like', '%'.$q.'%')->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'))))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn ($query, $status) => $query->whereHas('payments', fn ($payment) => $payment->where('status', $status)))
            ->when($filters['shipment_status'] ?? null, fn ($query, $status) => $query->whereHas('shipments', fn ($shipment) => $shipment->where('status', $status)))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100));
        return response()->json(['data' => $orders]);
    }

    public function show(AdminOrderRequest $request, int $order): JsonResponse
    {
        $item = CustomerOrder::query()->with(['user:id,name,email,phone', 'items.product:id,name,slug', 'items.variant:id,product_id,sku,price', 'payments.operations', 'shipments.method', 'shipments.events', 'shipments.operations', 'returns.items'])->findOrFail($order);
        return response()->json(['data' => $item]);
    }
}
