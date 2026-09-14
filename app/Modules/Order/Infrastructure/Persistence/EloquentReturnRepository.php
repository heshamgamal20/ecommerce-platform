<?php
namespace App\Modules\Order\Infrastructure\Persistence;
use App\Models\AuditLog;
use App\Models\CustomerOrder;
use App\Models\OrderReturn;
use App\Models\OutboxEvent;
use App\Modules\Order\Domain\Contracts\ReturnRepositoryInterface;
use App\Modules\Order\Domain\Exceptions\ReturnException;
use Illuminate\Support\Facades\DB;
final class EloquentReturnRepository implements ReturnRepositoryInterface
{
    public function createForCustomer(int $userId, int $orderId, array $data): object
    {
        return DB::transaction(function () use ($userId, $orderId, $data): object {
            $order = CustomerOrder::query()->with('items')->where('user_id', $userId)->find($orderId);
            if ($order === null || $order->status !== 'delivered') throw ReturnException::notAllowed();
            if (OrderReturn::query()->where('order_id', $orderId)->whereIn('status', ['pending', 'approved'])->exists()) throw ReturnException::alreadyRequested();
            $orderItems = $order->items->keyBy('id'); $refund = 0; $items = []; $requestedQuantities = [];
            foreach ($data['items'] as $item) {
                $orderItem = $orderItems->get((int) ($item['order_item_id'] ?? 0)); $quantity = (int) ($item['quantity'] ?? 0);
                $requestedQuantities[$orderItem?->id] = ($requestedQuantities[$orderItem?->id] ?? 0) + $quantity;
                if ($orderItem === null || $quantity < 1 || $requestedQuantities[$orderItem->id] > $orderItem->quantity) throw ReturnException::invalidItems();
                $refund += $orderItem->unit_price * $quantity;
                $items[$orderItem->id] = ['order_item_id' => $orderItem->id, 'product_id' => $orderItem->product_id, 'quantity' => $requestedQuantities[$orderItem->id], 'unit_price' => $orderItem->unit_price];
            }
            if ($items === []) throw ReturnException::invalidItems();
            $return = OrderReturn::query()->create(['order_id' => $orderId, 'payment_id' => $order->payments()->whereIn('status', ['paid', 'confirmed'])->latest('id')->value('id'), 'user_id' => $userId, 'status' => 'pending', 'reason' => $data['reason'], 'notes' => $data['notes'] ?? null, 'refund_amount' => $refund]);
            $return->items()->createMany(array_values($items));
            return $return->load('items');
        });
    }
    public function listForCustomer(int $userId): iterable { return OrderReturn::query()->with(['items','payment:id,order_id,method,status,provider_reference'])->where('user_id', $userId)->latest()->paginate(25); }
    public function listAll(): iterable { return OrderReturn::query()->with(['items','order:id,user_id,status','user:id,name'])->latest()->get(); }
    public function approve(int $returnId): object
    {
        return DB::transaction(function () use ($returnId): object {
            $return = OrderReturn::query()->lockForUpdate()->find($returnId);
            if ($return === null || $return->status !== 'pending') throw ReturnException::invalidTransition();
            $return->update(['status' => 'approved']);
            OutboxEvent::query()->firstOrCreate(['deduplication_key' => 'return:approved:' . $return->id], ['aggregate_type' => 'order_return', 'aggregate_id' => $return->id, 'event_type' => 'order.return.approved', 'status' => 'pending', 'payload' => ['return_id' => $return->id, 'order_id' => $return->order_id, 'refund_amount' => $return->refund_amount]]);
            AuditLog::query()->create(['actor_id' => auth()->id(), 'action' => 'order.return.approved', 'target_type' => OrderReturn::class, 'target_id' => $return->id, 'metadata' => ['refund_amount' => $return->refund_amount]]);
            return $return->fresh('items');
        });
    }
    public function reject(int $returnId, string $reason): object
    {
        return DB::transaction(function () use ($returnId, $reason): object {
            $return = OrderReturn::query()->lockForUpdate()->find($returnId);
            if ($return === null || $return->status !== 'pending') throw ReturnException::invalidTransition();
            $return->update(['status' => 'rejected', 'rejection_reason' => $reason]);
            AuditLog::query()->create(['actor_id' => auth()->id(), 'action' => 'order.return.rejected', 'target_type' => OrderReturn::class, 'target_id' => $return->id, 'metadata' => ['reason' => $reason]]);
            return $return->fresh('items');
        });
    }
}
