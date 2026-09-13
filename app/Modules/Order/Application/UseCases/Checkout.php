<?php

namespace App\Modules\Order\Application\UseCases;

use App\Modules\Auth\Domain\Contracts\AuthenticationServiceInterface;
use App\Modules\Auth\Domain\Exceptions\AuthenticationException;
use App\Modules\Order\Domain\ValueObjects\CheckoutData;
use App\Modules\Order\Domain\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Application\Services\CheckoutOrderService;
use App\Modules\Order\Domain\Contracts\TransactionManagerInterface;
use App\Modules\Payment\Application\UseCases\CreatePayment;
use App\Modules\Payment\Domain\ValueObjects\PaymentData;
use App\Modules\Shipping\Application\UseCases\CreateShipment;
use App\Modules\Shipping\Domain\ValueObjects\CreateShipmentData;
use App\Modules\Customer\Domain\Contracts\CustomerNotificationRepositoryInterface;
use App\Modules\Customer\Domain\Contracts\CustomerAccountServiceInterface;
final class Checkout
{
    public function __construct(
        private readonly AuthenticationServiceInterface $authentication,
        private readonly OrderRepositoryInterface $orders,
        private readonly CheckoutOrderService $checkoutOrders,
        private readonly TransactionManagerInterface $transactions,
        private readonly CreateShipment $createShipment,
        private readonly CreatePayment $createPayment,
        private readonly CustomerNotificationRepositoryInterface $notifications,
        private readonly CustomerAccountServiceInterface $accounts,
    ) {}

    public function execute(CheckoutData $data): object
    {
        $user = $this->authentication->user();
        $checkoutUser = $user;
        if ($user === null && $data->guestItems === []) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Keep only local order, inventory, and shipment state in this
        // transaction. A remote gateway call must never run under it: a
        // database rollback cannot undo a successful external charge.
        $shippingFee = $this->checkoutOrders->shippingFee($data->shippingMethodId, $data->currency);

        $order = $this->transactions->run(function () use ($data, $user, $shippingFee, &$checkoutUser): object {
            if ($user === null && $data->createAccount) {
                $accountDetails = $data->guestDetails;
                $accountDetails['password'] = $data->accountPassword;
                $checkoutUser = $this->accounts->createFromGuestData($accountDetails);
            }

            $order = $user === null
                ? $this->checkoutOrders->forGuest($data->guestItems, $data->guestDetails, $data->currency, $data->idempotencyKey, $data->couponCode, $checkoutUser?->id, $shippingFee)
                : $this->checkoutOrders->forUser($user, $data->addressId, $data->currency, $data->idempotencyKey, $data->couponCode, $shippingFee);

            if ($data->shippingMethodId !== null) {
                $shipment = $this->createShipment->execute($order->id, new CreateShipmentData(
                    $data->shippingMethodId,
                    $data->shippingIdempotencyKey ?? $data->idempotencyKey ?? ('shipment-' . $order->id),
                ));
            }

            return $order;
        });

        if ($user === null && $checkoutUser !== null) {
            $this->authentication->login($checkoutUser);
        }

        if ($data->paymentMethod !== null) {
            $this->createPayment->execute($order->id, new PaymentData(
                method: $data->paymentMethod,
                currency: $order->currency,
                idempotencyKey: $data->paymentIdempotencyKey ?? $data->idempotencyKey ?? ('payment-' . $order->id),
                amount: $order->total_amount,
            ));
        }

        $result = $checkoutUser === null ? $this->orders->find($order->id) : $this->orders->findForUser($checkoutUser->id, $order->id);
        if ($checkoutUser !== null) {
            $this->notifications->createForUser(
                (int) $checkoutUser->id,
                'order.created',
                'Order received',
                'Your order has been received and is being prepared.',
            );
        }
        return $result;
    }
}
