<?php

namespace App\Modules\Order\Infrastructure\Persistence;

use App\Models\CustomerOrder;
use App\Modules\Inventory\Domain\Contracts\InventoryRepositoryInterface;
use App\Modules\Order\Domain\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Domain\OrderLifecycle;
use App\Modules\Order\Domain\Exceptions\OrderActionNotAllowedException;
use App\Modules\Order\Domain\Exceptions\OrderNotFoundException;
use Illuminate\Support\Facades\DB;

final class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private readonly InventoryRepositoryInterface $inventory) {}


    public function listForUser(int $userId): iterable
    {
        return CustomerOrder::query()->with('items.product')->where('user_id', $userId)->latest()->get();
    }

    public function listAll(): iterable
    {
        return CustomerOrder::query()->with(['user', 'items.product'])->latest()->get();
    }

    public function findForUser(int $userId, int $orderId): object
    {
        $order = CustomerOrder::query()->with('items.product')->where('user_id', $userId)->find($orderId);
        if ($order === null) {
            throw new OrderNotFoundException('Order not found.');
        }

        return $order;
    }

    public function find(int $orderId): object
    {
        $order = CustomerOrder::query()->with(['user', 'items.product'])->find($orderId);
        if ($order === null) {
            throw new OrderNotFoundException('Order not found.');
        }

        return $order;
    }

    public function updateStatus(int $orderId, string $status): object
    {
        return DB::transaction(function () use ($orderId, $status): object {
            $order = CustomerOrder::query()->lockForUpdate()->find($orderId);
            if ($order === null) {
                throw new OrderNotFoundException('Order not found.');
            }
            // A retry of the same transition is a successful no-op. The row lock
            // guarantees only the first shipped transition can commit inventory.
            if ((string) $order->status === $status) {
                return $order->fresh(['user', 'items.product']);
            }
            OrderLifecycle::assertCanTransition((string) $order->status, $status);
            if ($status === 'shipped') {
                foreach ($order->items as $item) {
                    $this->inventory->commit($item->product_id, $item->variant_id, $item->quantity);
                }
            }
            $order->update(['status' => $status]);

            return $order->fresh(['user', 'items.product']);
        });
    }

    public function cancelForUser(int $userId, int $orderId): object
    {
        return DB::transaction(function () use ($userId, $orderId): object {
            $order = CustomerOrder::query()->where('user_id', $userId)->lockForUpdate()->find($orderId);
            if ($order === null) {
                throw new OrderNotFoundException('Order not found.');
            }
            // Cancellation is idempotent: a retry must not release the same
            // reservation again. The row lock serializes concurrent retries.
            if ((string) $order->status === 'cancelled') {
                return $order->fresh(['items.product', 'items.variant']);
            }
            // Cancellation is idempotent: a retry must not release the same
            // reservation again. The row lock serializes concurrent retries.
            if ((string) $order->status === 'cancelled') {
                return $order->fresh(['items.product', 'items.variant']);
            }
            if (! OrderLifecycle::canCancel((string) $order->status)) {
                throw new OrderActionNotAllowedException('This order can no longer be cancelled.');
            }
            foreach ($order->items as $item) {
                $this->inventory->release($item->product_id, $item->variant_id, $item->quantity);
            }
            $order->update(['status' => 'cancelled']);

            return $order->fresh(['items.product', 'items.variant']);
        });
    }

    public function cancel(int $orderId): object
    {
        return DB::transaction(function () use ($orderId): object {
            $order = CustomerOrder::query()->lockForUpdate()->find($orderId);
            if ($order === null) {
                throw new OrderNotFoundException('Order not found.');
            }
            if (! OrderLifecycle::canCancel((string) $order->status)) {
                throw new OrderActionNotAllowedException('This order can no longer be cancelled.');
            }
            foreach ($order->items as $item) {
                $this->inventory->release($item->product_id, $item->variant_id, $item->quantity);
            }
            $order->update(['status' => 'cancelled']);

            return $order->fresh(['items.product', 'items.variant']);
        });
    }

    public function addShippingFee(int $orderId, int $fee, int $total): object
    {
        return DB::transaction(function () use ($orderId, $fee, $total): CustomerOrder {
            $order = CustomerOrder::query()->lockForUpdate()->find($orderId);
            if ($order === null) {
                throw new OrderNotFoundException('Order not found.');
            }
            $shippingAmount = $order->shipping_amount + $fee;
            $order->update([
                'shipping_amount' => $shippingAmount,
                'total_amount' => $total,
            ]);

            return $order->fresh(['items.product', 'items.variant', 'payments', 'shipments']);
        });
    }

    public function markRefunded(int $orderId): object
    {
        return DB::transaction(function () use ($orderId): object {
            $order = CustomerOrder::query()->lockForUpdate()->find($orderId);
            if ($order === null) {
                throw new OrderNotFoundException('Order not found.');
            }
            if (in_array($order->status, ['cancelled', 'refunded'], true)) {
                throw new OrderActionNotAllowedException('This order cannot be refunded.');
            }
            $order->update(['status' => 'refunded']);

            return $order->fresh(['items.product']);
        });
    }
}
