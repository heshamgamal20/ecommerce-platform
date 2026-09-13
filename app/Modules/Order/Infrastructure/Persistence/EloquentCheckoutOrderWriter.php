<?php
namespace App\Modules\Order\Infrastructure\Persistence;

use App\Models\Coupon;
use App\Models\CustomerOrder;
use App\Modules\Order\Domain\Contracts\CheckoutOrderWriterInterface;

final class EloquentCheckoutOrderWriter implements CheckoutOrderWriterInterface
{
    public function findByIdempotencyKey(?string $key): ?object
    {
        return $key === null ? null : CustomerOrder::query()->where('idempotency_key', $key)->first()?->load('items');
    }

    public function create(array $attributes, array $items, ?string $couponCode, int $discount, ?int $userId): object
    {
        $order = CustomerOrder::query()->create($attributes);
        $order->items()->createMany($items);
        if ($couponCode !== null) {
            Coupon::query()->where('code', $couponCode)->firstOrFail()->usages()->create([
                'user_id' => $userId,
                'order_id' => $order->id,
                'discount_amount' => $discount,
            ]);
        }
        return $order->load('items');
    }
}
