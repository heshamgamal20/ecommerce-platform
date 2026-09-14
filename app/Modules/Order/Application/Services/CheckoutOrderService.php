<?php
namespace App\Modules\Order\Application\Services;

use App\Modules\Catalog\Domain\Contracts\CheckoutProductReaderInterface;
use App\Modules\Inventory\Domain\Contracts\InventoryRepositoryInterface;
use App\Modules\Order\Domain\Contracts\CheckoutOrderWriterInterface;
use App\Modules\Order\Domain\Contracts\PricingCalculatorInterface;
use App\Modules\Order\Domain\Exceptions\CheckoutException;
use App\Modules\Order\Domain\Exceptions\CheckoutIdempotencyConflictException;
use App\Modules\Promotion\Domain\Contracts\CouponServiceInterface;
use App\Modules\Tax\Domain\Contracts\TaxCalculatorInterface;
use App\Modules\Shipping\Domain\Contracts\ShippingMethodRepositoryInterface;
use App\Modules\Shipping\Domain\Exceptions\ShippingException;

final class CheckoutOrderService
{
    public function __construct(
        private readonly CheckoutOrderWriterInterface $orders,
        private readonly InventoryRepositoryInterface $inventory,
        private readonly CouponServiceInterface $coupons,
        private readonly TaxCalculatorInterface $taxes,
        private readonly CheckoutProductReaderInterface $products,
        private readonly PricingCalculatorInterface $pricing,
        private readonly ShippingMethodRepositoryInterface $shippingMethods,
    ) {}

    public function forUser(object $user, int $addressId, string $currency, ?string $idempotencyKey, ?string $couponCode, int $shippingFee = 0): object
    {
        $existing = $this->orders->findByIdempotencyKey($idempotencyKey);
        if ($existing !== null) {
            if ((int) $existing->user_id !== (int) $user->id) throw CheckoutException::idempotencyKeyConflict();
            return $existing;
        }
        $user->loadMissing(['cart.items.product', 'cart.items.variant', 'addresses']);
        $items = $user->cart?->items ?? collect();
        if ($items->isEmpty()) throw CheckoutException::emptyCart();
        $address = $user->addresses()->findOrFail($addressId);
        $lines = [];
        foreach ($items as $item) {
            $lines[] = $this->line($item->product, $item->variant, (int) $item->quantity);
        }
        $addressData = [
            'recipient_name' => $address->recipient_name, 'phone' => $address->phone,
            'address_line1' => $address->address_line1, 'address_line2' => $address->address_line2,
            'city' => $address->city, 'state' => $address->state, 'postal_code' => $address->postal_code,
            'country' => $address->country,
        ];
        $order = $this->makeOrder($lines, $currency, $idempotencyKey, $couponCode, $user->id, $addressData, null, $shippingFee);
        $user->cart?->items()->delete();
        return $order;
    }

    public function forGuest(array $input, array $details, string $currency, ?string $idempotencyKey, ?string $couponCode, ?int $userId = null, int $shippingFee = 0, ?string $guestCheckoutToken = null): object
    {
        $existing = $this->orders->findByIdempotencyKey($idempotencyKey);
        if ($existing !== null) {
            if ((int) ($existing->user_id ?? 0) !== (int) ($userId ?? 0)) {
                throw new CheckoutIdempotencyConflictException();
            }
            if ($userId === null && ($guestCheckoutToken === null || $existing->guest_checkout_token_hash === null
                || ! hash_equals((string) $existing->guest_checkout_token_hash, hash('sha256', $guestCheckoutToken)))) {
                throw new CheckoutIdempotencyConflictException();
            }
            if ($userId === null) {
                $existing->setAttribute('guest_checkout_token', $guestCheckoutToken);
            }
            return $existing;
        }
        $lines = [];
        foreach ($input as $item) {
            $product = $this->products->findForCheckout((int) $item['product_id']);
            $variant = isset($item['variant_id']) ? $product?->variants->firstWhere('id', (int) $item['variant_id']) : null;
            $lines[] = $this->line($product, $variant, (int) $item['quantity']);
        }
        $country = strtoupper((string) ($details['country'] ?? 'EG'));
        return $this->makeOrder($lines, $currency, $idempotencyKey, $couponCode, $userId, [
            'recipient_name' => $details['name'], 'phone' => $details['phone'],
            'address_line1' => $details['address_line1'], 'address_line2' => $details['address_line2'] ?? null,
            'city' => $details['city'], 'state' => $details['state'] ?? null,
            'postal_code' => $details['postal_code'] ?? null, 'country' => $country,
        ], $details['email'] ?? null, $shippingFee, $guestCheckoutToken);
    }

    public function shippingFee(?int $shippingMethodId, string $currency): int
    {
        if ($shippingMethodId === null) return 0;
        $method = $this->shippingMethods->find($shippingMethodId);
        if (! $method->is_active) throw new ShippingException('Shipping method is inactive.');
        if ($method->currency !== $currency) throw new ShippingException('Shipping currency does not match the order.');
        return (int) $method->base_fee;
    }

    private function line(?object $product, ?object $variant, int $quantity): array
    {
        if ($product === null || $product->status !== 'active') throw CheckoutException::unavailableProduct($product?->name ?? 'unknown');
        if ($quantity < 1) throw CheckoutException::emptyCart();
        if ($product->type === 'variable' && ($variant === null || $variant->status !== 'active')) throw CheckoutException::unavailableProduct($product->name);
        $price = $variant?->price ?? $product->price;
        if ($price === null || $price < 0) throw CheckoutException::missingPrice($product->name);
        $this->inventory->reserve($product->id, $variant?->id, $quantity);
        $total = $this->pricing->lineTotal($price, $quantity);
        $purchasePrice = $variant?->purchase_price ?? $product->purchase_price;
        return ['product_id' => $product->id, 'variant_id' => $variant?->id, 'name' => $product->name, 'sku' => $variant?->sku, 'quantity' => $quantity, 'unit_price' => $price, 'purchase_price' => $purchasePrice, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => $total, ];
    }

    public function totalWithShipping(object $order, int $shipping): int
    {
        return $this->pricing->total(
            (int) $order->subtotal_amount,
            (int) $order->discount_amount,
            (int) $order->tax_amount,
            (int) $shipping,
        )['total'];
    }

    private function makeOrder(array $lines, string $currency, ?string $key, ?string $couponCode, ?int $userId, array $address, ?string $email = null, int $shippingFee = 0, ?string $guestCheckoutToken = null): object
    {
        $subtotal = $this->pricing->subtotal($lines);
        $promotion = $this->coupons->apply($couponCode, (int) ($userId ?? 0), $subtotal);
        $tax = $this->taxes->calculate($this->pricing->taxableSubtotal($subtotal, $promotion['discount']), (string) $address['country'], $address['state'] ?? null);
        $totals = $this->pricing->total($subtotal, $promotion['discount'], $tax['amount'], $shippingFee);
        $cleanLines = $lines;
        return $this->orders->create([
            'user_id' => $userId, 'guest_email' => $userId === null ? $email : null, 'guest_phone' => $userId === null ? $address['phone'] : null, 'status' => 'pending',
            'total_amount' => $totals['total'], 'subtotal_amount' => $totals['subtotal'],
            'discount_amount' => $totals['discount'], 'coupon_code' => $promotion['code'], 'tax_amount' => $tax['amount'],
            'tax_rate' => $tax['rate'], 'tax_rule_id' => $tax['rule_id'], 'shipping_amount' => $totals['shipping'], 'currency' => $currency,
            'shipping_address' => $address, 'idempotency_key' => $key,
            'guest_checkout_token_hash' => $userId === null && $guestCheckoutToken !== null ? hash('sha256', $guestCheckoutToken) : null,
        ], $cleanLines, $promotion['code'], $promotion['discount'], $userId);
    }
}
