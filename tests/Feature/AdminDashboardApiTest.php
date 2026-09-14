<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_read_unified_dashboard_metrics(): void
    {
        $this->seed(RbacSeeder::class);
        $manager = $this->userWithRole('manager');
        $order = CustomerOrder::query()->create(['user_id' => $manager->id, 'status' => 'confirmed', 'total_amount' => 1250, 'subtotal_amount' => 1100, 'discount_amount' => 50, 'tax_amount' => 100, 'shipping_amount' => 100, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);
        Payment::query()->create(['order_id' => $order->id, 'user_id' => $manager->id, 'method' => 'cash_on_delivery', 'amount' => 1250, 'currency' => 'EGP', 'status' => 'paid', 'idempotency_key' => 'dashboard-payment-1']);
        $method = ShippingMethod::query()->create(['code' => 'dash-carrier', 'name' => 'Dashboard Carrier', 'carrier' => 'Dashboard Carrier', 'base_fee' => 100, 'currency' => 'EGP', 'is_active' => true]);
        Shipment::query()->create(['order_id' => $order->id, 'user_id' => $manager->id, 'shipping_method_id' => $method->id, 'method_code' => $method->code, 'fee' => 100, 'currency' => 'EGP', 'status' => 'in_transit', 'address_snapshot' => ['city' => 'Cairo'], 'idempotency_key' => 'dashboard-shipment-1']);

        $this->actingAs($manager)->getJson('/api/v1/admin/dashboard?from=2026-01-01&to=2027-01-01')->assertOk()->assertJsonPath('data.sales.orders', 1)->assertJsonPath('data.sales.gross_amount', 1250)->assertJsonPath('data.payments.collected_amount', 1250)->assertJsonPath('data.shipping.open_shipments', 1);
    }

    public function test_support_agent_cannot_read_unified_dashboard(): void
    {
        $this->seed(RbacSeeder::class);
        $support = $this->userWithRole('support_agent');
        $this->actingAs($support)->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());
        return $user;
    }
}
