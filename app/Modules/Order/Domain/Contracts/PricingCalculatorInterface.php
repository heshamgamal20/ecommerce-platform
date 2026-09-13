<?php
namespace App\Modules\Order\Domain\Contracts;

interface PricingCalculatorInterface
{
    public function lineTotal(int|float $unitPrice, int $quantity): int;

    /** @param list<array{quantity:int,unit_price:int|float}> $lines */
    public function subtotal(array $lines): int;

    public function taxableSubtotal(int $subtotal, int $discount): int;

    /** @return array{subtotal:int,discount:int,tax:int,shipping:int,total:int} */
    public function total(int $subtotal, int $discount, int $tax, int $shipping = 0): array;
}
