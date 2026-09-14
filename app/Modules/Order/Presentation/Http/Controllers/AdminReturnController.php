<?php

namespace App\Modules\Order\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrderReturn;
use App\Modules\Order\Presentation\Http\Requests\AdminReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class AdminReturnController extends Controller
{
    public function index(AdminReturnRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $returns = OrderReturn::query()->with(['order:id,user_id,status,total_amount,currency', 'user:id,name,email,phone', 'items.orderItem:id,order_id,name,sku,quantity,unit_price'])
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($inner) => $inner->where('id', is_numeric($q) ? (int) $q : 0)->orWhereHas('order', fn ($order) => $order->where('id', is_numeric($q) ? (int) $q : 0))->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'))))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['inspection_status'] ?? null, fn ($query, $status) => $query->where('inspection_status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100));
        return response()->json(['data' => $returns]);
    }

    public function show(AdminReturnRequest $request, int $return): JsonResponse
    {
        $item = OrderReturn::query()->with(['order.user:id,name,email,phone', 'order.payments:id,order_id,method,amount,currency,status,provider_reference', 'user:id,name,email,phone', 'receiver:id,name,email', 'inspector:id,name,email', 'items.orderItem'])->findOrFail($return);
        return response()->json(['data' => $item]);
    }

    public function receive(AdminReturnRequest $request, int $return): JsonResponse
    {
        $item = DB::transaction(function () use ($request, $return): OrderReturn {
            $item = OrderReturn::query()->lockForUpdate()->findOrFail($return);
            abort_unless($item->status === 'approved' && $item->received_at === null, 409, 'Return must be approved and not previously received.');
            $item->update(['received_at' => now(), 'received_by' => $request->user()->id, 'inspection_notes' => $request->validated('notes')]);
            AuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'order.return.received', 'target_type' => OrderReturn::class, 'target_id' => $item->id, 'metadata' => ['notes' => $request->validated('notes')]]);
            return $item->fresh(['items']);
        });
        return response()->json(['data' => $item]);
    }

    public function inspect(AdminReturnRequest $request, int $return): JsonResponse
    {
        $item = DB::transaction(function () use ($request, $return): OrderReturn {
            $data = $request->validated();
            $item = OrderReturn::query()->lockForUpdate()->findOrFail($return);
            abort_unless($item->received_at !== null && $item->inspection_status === 'pending', 409, 'Return must be received and not previously inspected.');
            abort_unless((int) $data['final_refund_amount'] <= (int) $item->refund_amount, 422, 'Final refund cannot exceed requested refund.');
            $item->update(['inspection_status' => $data['inspection_status'], 'inspection_notes' => $data['inspection_notes'] ?? $item->inspection_notes, 'final_refund_amount' => $data['final_refund_amount'], 'inspected_at' => now(), 'inspected_by' => $request->user()->id]);
            AuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'order.return.inspected', 'target_type' => OrderReturn::class, 'target_id' => $item->id, 'metadata' => ['inspection_status' => $data['inspection_status'], 'final_refund_amount' => $data['final_refund_amount']]]);
            return $item->fresh(['items']);
        });
        return response()->json(['data' => $item]);
    }
}
