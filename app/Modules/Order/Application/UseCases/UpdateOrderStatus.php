<?php

namespace App\Modules\Order\Application\UseCases;

use App\Modules\Order\Domain\Contracts\OrderRepositoryInterface;
use App\Modules\Customer\Domain\Contracts\CustomerNotificationRepositoryInterface;

final class UpdateOrderStatus
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly CustomerNotificationRepositoryInterface $notifications,
    ) {}

    public function execute(int $orderId, string $status): object
    {
        $order = $status === 'cancelled'
            ? $this->orders->cancel($orderId)
            : $this->orders->updateStatus($orderId, $status);

        $messages = [
            'confirmed' => ['order.confirmed', 'Order confirmed', 'Your order has been confirmed.'],
            'processing' => ['order.processing', 'Order processing', 'Your order is being prepared.'],
            'shipped' => ['order.shipped', 'Order shipped', 'Your order has been shipped.'],
            'delivered' => ['order.delivered', 'Order delivered', 'Your order has been delivered.'],
            'cancelled' => ['order.cancelled', 'Order cancelled', 'Your order has been cancelled.'],
        ];
        if (isset($messages[$status]) && isset($order->user_id)) {
            [$type, $title, $body] = $messages[$status];
            $this->notifications->createForUser((int) $order->user_id, $type, $title, $body);
        }

        return $order;
    }
}
