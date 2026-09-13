<?php

namespace App\Modules\Order\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Domain\ValueObjects\CheckoutData;
use App\Modules\Order\Application\UseCases\CreateOrder;
use App\Modules\Order\Presentation\Http\Requests\CheckoutRequest;
use Illuminate\Http\JsonResponse;

final class CheckoutController extends Controller
{
    public function __invoke(CheckoutRequest $request, CreateOrder $createOrder): JsonResponse
    {
        $data = $request->validated();

        $order = $createOrder->execute(new CheckoutData(
            addressId: isset($data['address_id']) ? (int) $data['address_id'] : null,
            currency: strtoupper($data['currency'] ?? 'EGP'),
            idempotencyKey: $data['idempotency_key'] ?? null,
            shippingMethodId: isset($data['shipping_method_id']) ? (int) $data['shipping_method_id'] : null,
            shippingIdempotencyKey: $data['shipping_idempotency_key'] ?? null,
            paymentMethod: $data['payment_method'] ?? null,
            paymentIdempotencyKey: $data['payment_idempotency_key'] ?? null,
            couponCode: $data['coupon_code'] ?? null,
            guestItems: $data['items'] ?? [],
            guestDetails: $data['guest'] ?? [],
            createAccount: (bool) ($data['guest']['create_account'] ?? false),
            accountPassword: $data['guest']['password'] ?? null,
        ));

        return response()->json(['data' => $order], 201);
    }
}
