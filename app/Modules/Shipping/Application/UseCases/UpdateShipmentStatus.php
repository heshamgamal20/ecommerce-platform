<?php

namespace App\Modules\Shipping\Application\UseCases;

use App\Modules\Auth\Domain\Contracts\AuthenticationServiceInterface;
use App\Modules\Auth\Domain\Exceptions\AuthenticationException;
use App\Modules\Order\Domain\Contracts\OrderRepositoryInterface;
use App\Modules\Customer\Domain\Contracts\CustomerNotificationRepositoryInterface;
use App\Modules\Shipping\Domain\Contracts\ShipmentRepositoryInterface;

final class UpdateShipmentStatus
{
    public function __construct(private readonly AuthenticationServiceInterface $authentication, private readonly ShipmentRepositoryInterface $shipments, private readonly OrderRepositoryInterface $orders, private readonly CustomerNotificationRepositoryInterface $notifications) {}
    public function execute(int $shipmentId, string $status, ?string $note = null): object
    {
        $user = $this->authentication->user();
        if ($user === null) throw new AuthenticationException('Unauthenticated.');
        $shipment = $this->shipments->updateStatus($this->shipments->find($shipmentId), $status, $user->id, $note);
        if ($status === 'delivered') {
            $order = $this->orders->find($shipment->order_id);
            if ($order->status === 'shipped') {
                $this->orders->updateStatus($order->id, 'delivered');
            }
        }
        $labels = [
            'picked_up' => ['shipment.picked_up', 'Shipment picked up', 'The carrier has picked up your shipment.'],
            'in_transit' => ['shipment.in_transit', 'Shipment in transit', 'Your shipment is on its way.'],
            'out_for_delivery' => ['shipment.out_for_delivery', 'Out for delivery', 'Your shipment is out for delivery.'],
            'delivered' => ['shipment.delivered', 'Shipment delivered', 'Your shipment has been delivered.'],
            'cancelled' => ['shipment.cancelled', 'Shipment cancelled', 'Your shipment has been cancelled.'],
        ];
        if (isset($labels[$status]) && isset($shipment->user_id)) {
            [$type, $title, $body] = $labels[$status];
            $this->notifications->createForUser((int) $shipment->user_id, $type, $title, $body);
        }
        return $shipment;
    }
}
