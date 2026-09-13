<?php
namespace App\Modules\Order\Infrastructure\Pricing;

use App\Modules\Order\Domain\Contracts\PricingCalculatorInterface;

final class DefaultPricingCalculator implements PricingCalculatorInterface
{
    public function lineTotal(int|float $unitPrice, int $quantity): int
    {
        return (int) round($unitPrice * $quantity);
    }

    public function subtotal(array $lines): int
    {
        return array_sum(array_map(
            fn (array $line): int => $this->lineTotal($line['unit_price'], $line['quantity']),
            $lines,
        ));
    }

    public function taxableSubtotal(int $subtotal, int $discount): int
    {
        return max(0, $subtotal - max(0, $discount));
    }

    public function total(int $subtotal, int $discount, int $tax, int $shipping = 0): array
    {
        return [
            'subtotal' => $subtotal,
            'discount' => max(0, $discount),
            'tax' => max(0, $tax),
            'shipping' => max(0, $shipping),
            'total' => $subtotal - max(0, $discount) + max(0, $tax) + max(0, $shipping),
        ];
    }
}
