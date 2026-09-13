<?php

namespace App\Modules\Order\Domain;

use App\Models\CustomerOrder;
use App\Models\User;

final class OrderPolicy
{
    public function view(User $user, CustomerOrder $order): bool
    {
        return $this->isStaff($user) || (int) $order->user_id === (int) $user->id;
    }

    public function cancel(User $user, CustomerOrder $order): bool
    {
        return (int) $order->user_id === (int) $user->id && OrderLifecycle::canCancel((string) $order->status);
    }

    public function manageStatus(User $user, CustomerOrder $order): bool
    {
        return $this->isStaff($user);
    }

    private function isStaff(User $user): bool
    {
        return $user->roles()->whereIn('slug', ['admin', 'order_manager'])->exists();
    }
}
