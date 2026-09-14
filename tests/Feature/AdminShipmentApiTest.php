<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShipmentOperation;
use App\Models\ShippingMethod;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminShipmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_filter_shipments_and_view_events_and_operations(): void
    {
        $this->seed(RbacSeeder::class);
        $manager = $this->userWithRole('manager');
        $customer = $this->userWithRole('customer');
        $order = CustomerOrder::query()->create(['user_id' => $customer->id, 'status' => 'processing', 'total_amount' => 800, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);
        $method = ShippingMethod::query()->create(['code' => 'admin-carrier', 'name' => 'Admin Carrier', 'carrier' => 'Bosta', 'base_fee' => 100, 'currency' => 'EGP', 'is_active' => true]);
        $shipment = Shipment::query()->create(['order_id' => $order->id, 'user_id' => $customer->id, 'shipping_method_id' => $method->id, 'method_code' => $method->code, 'tracking_number' => 'TRK-ADMIN-1', 'fee' => 100, 'currency' => 'EGP', 'status' => 'in_transit', 'address_snapshot' => ['city' => 'Cairo'], 'idempotency_key' => 'admin-shipment-1']);
        ShipmentEvent::query()->create(['shipment_id' => $shipment->id, 'from_status' => 'picked_up', 'to_status' => 'in_transit', 'actor_id' => $manager->id, 'note' => 'In transit']);
        ShipmentOperation::query()->create(['shipment_id' => $shipment->id, 'operation' => 'track', 'status' => 'failed', 'idempotency_key' => 'track-1', 'attempt_count' => 2, 'last_error' => 'Provider timeout']);

        $this->actingAs($manager)->getJson('/api/v1/admin/shipments?q=TRK-ADMIN-1&carrier=Bosta&status=in_transit')->assertOk()->assertJsonPath('data.data.0.id', $shipment->id)->assertJsonPath('data.data.0.events_count', 1);
        $this->actingAs($manager)->getJson('/api/v1/admin/shipments/'.$shipment->id)->assertOk()->assertJsonPath('data.id', $shipment->id)->assertJsonPath('data.events.0.to_status', 'in_transit')->assertJsonPath('data.operations.0.last_error', 'Provider timeout');
        $this->actingAs($manager)->getJson('/api/v1/admin/shipments/exceptions?carrier=Bosta')->assertOk()->assertJsonPath('data.data.0.id', $shipment->id);
    }

    public function test_customer_cannot_access_admin_shipments(): void
    {
        $this->seed(RbacSeeder::class);
        $customer = $this->userWithRole('customer');
        $this->actingAs($customer)->getJson('/api/v1/admin/shipments')->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());
        return $user;
    }
}
