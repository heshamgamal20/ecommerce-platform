<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_manager_can_filter_orders_and_view_full_order_context(): void
    {
        $this->seed(RbacSeeder::class);
        $manager = $this->userWithRole('order_manager');
        $customer = $this->userWithRole('customer');
        $order = CustomerOrder::query()->create(['user_id' => $customer->id, 'status' => 'processing', 'total_amount' => 1500, 'subtotal_amount' => 1400, 'shipping_amount' => 100, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);
        Payment::query()->create(['order_id' => $order->id, 'user_id' => $customer->id, 'method' => 'cash_on_delivery', 'amount' => 1500, 'currency' => 'EGP', 'status' => 'paid', 'idempotency_key' => 'admin-order-payment-1']);

        $this->actingAs($manager)->getJson('/api/v1/admin/orders?status=processing&payment_status=paid')->assertOk()->assertJsonPath('data.data.0.id', $order->id)->assertJsonPath('data.data.0.payments_count', 1);
        $this->actingAs($manager)->getJson('/api/v1/admin/orders/'.$order->id)->assertOk()->assertJsonPath('data.id', $order->id)->assertJsonPath('data.payments.0.status', 'paid');
    }

    public function test_customer_cannot_access_admin_order_directory(): void
    {
        $this->seed(RbacSeeder::class);
        $customer = $this->userWithRole('customer');
        $this->actingAs($customer)->getJson('/api/v1/admin/orders')->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());
        return $user;
    }
}
