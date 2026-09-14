<?php

namespace App\Modules\Customer\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Customer\Presentation\Http\Requests\AdminCustomerRequest;
use Illuminate\Http\JsonResponse;

final class AdminCustomerController extends Controller
{
    public function index(AdminCustomerRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $customers = User::query()->select(['id', 'name', 'email', 'phone', 'status', 'created_at'])
            ->whereHas('roles', fn ($query) => $query->where('slug', 'customer'))
            ->withCount('orders')
            ->withSum(['orders as total_spend' => fn ($query) => $query->whereNotIn('status', ['cancelled', 'refunded'])], 'total_amount')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100));
        return response()->json(['data' => $customers]);
    }

    public function show(AdminCustomerRequest $request, int $customer): JsonResponse
    {
        $user = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'customer'))->with(['orders' => fn ($query) => $query->latest('id')->limit(20), 'orders.payments', 'orders.returns'])->findOrFail($customer);
        $orders = $user->orders;
        return response()->json(['data' => ['customer' => $user->only(['id', 'name', 'email', 'phone', 'status', 'created_at']), 'summary' => ['orders' => $orders->count(), 'total_spend' => $orders->whereNotIn('status', ['cancelled', 'refunded'])->sum('total_amount'), 'average_order_value' => $orders->count() ? (int) round($orders->avg('total_amount')) : 0, 'returns' => $orders->flatMap->returns->count(), 'refund_amount' => $orders->flatMap->returns->whereNotIn('status', ['rejected', 'cancelled'])->sum('refund_amount'), 'payments' => $orders->flatMap->payments->count()], 'recent_orders' => $orders->values()]]);
    }
}
