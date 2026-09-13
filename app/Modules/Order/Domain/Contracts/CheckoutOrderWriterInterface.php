<?php
namespace App\Modules\Order\Domain\Contracts;

interface CheckoutOrderWriterInterface
{
    public function findByIdempotencyKey(?string $key): ?object;

    /** @param list<array<string,mixed>> $items */
    public function create(array $attributes, array $items, ?string $couponCode, int $discount, ?int $userId): object;
}
