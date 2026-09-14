<?php

namespace App\Modules\Shipping\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Modules\Shipping\Presentation\Http\Requests\AdminShipmentRequest;
use Illuminate\Http\JsonResponse;

final class AdminShipmentController extends Controller
{
    public function index(AdminShipmentRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $shipments = Shipment::query()->with(['order:id,status,total_amount,currency', 'user:id,name,email,phone', 'method:id,name,carrier,code'])
            ->withCount(['events', 'operations'])
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($inner) => $inner->where('tracking_number', 'like', '%'.$q.'%')->orWhere('id', is_numeric($q) ? (int) $q : 0)->orWhereHas('order', fn ($order) => $order->where('id', is_numeric($q) ? (int) $q : 0))->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'))))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['carrier'] ?? null, fn ($query, $carrier) => $query->whereHas('method', fn ($method) => $method->where('carrier', $carrier)))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100));
        return response()->json(['data' => $shipments]);
    }

    public function show(AdminShipmentRequest $request, int $shipment): JsonResponse
    {
        $item = Shipment::query()->with(['order:id,status,total_amount,currency', 'user:id,name,email,phone', 'method', 'events.actor:id,name,email', 'operations' => fn ($query) => $query->select(['id', 'shipment_id', 'operation', 'status', 'provider_reference', 'attempt_count', 'last_error', 'next_retry_at', 'created_at', 'updated_at'])])->findOrFail($shipment);
        return response()->json(['data' => $item]);
    }

    public function exceptions(AdminShipmentRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $hours = (int) ($filters['older_than_hours'] ?? 48);
        $cutoff = now()->subHours($hours);
        $shipments = Shipment::query()->with(['order:id,status,total_amount,currency', 'user:id,name,email,phone', 'method:id,name,carrier,code', 'operations' => fn ($query) => $query->whereIn('status', ['failed', 'dead_letter'])->select(['id', 'shipment_id', 'operation', 'status', 'attempt_count', 'last_error', 'next_retry_at', 'created_at'])])->where(function ($query) use ($cutoff): void {
            $query->whereIn('status', ['failed', 'cancelled'])->orWhere(fn ($open) => $open->whereIn('status', ['pending', 'processing', 'provider_created', 'picked_up', 'in_transit', 'out_for_delivery'])->where('created_at', '<=', $cutoff))->orWhereHas('operations', fn ($operation) => $operation->whereIn('status', ['failed', 'dead_letter']));
        })->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->when($filters['carrier'] ?? null, fn ($query, $carrier) => $query->whereHas('method', fn ($method) => $method->where('carrier', $carrier)))->latest('id')->paginate(min(max((int) ($filters['per_page'] ?? 50), 1), 100));
        return response()->json(['data' => $shipments, 'older_than_hours' => $hours]);
    }
}
