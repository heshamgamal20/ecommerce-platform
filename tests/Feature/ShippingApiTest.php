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

final class ShippingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_list_methods_create_shipment_and_list_own_shipments(): void
    {
        $this->seed(RbacSeeder::class);
        $customer = $this->userWithRole('customer');
        $method = ShippingMethod::query()->create(['code' => 'standard', 'name' => 'Standard', 'carrier' => 'Local', 'base_fee' => 150, 'currency' => 'EGP', 'is_active' => true]);
        $order = CustomerOrder::query()->create(['user_id' => $customer->id, 'status' => 'pending', 'total_amount' => 1000, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);

        $this->actingAs($customer)->getJson('/api/v1/customer/shipping-methods')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($customer)->postJson("/api/v1/customer/orders/{$order->id}/shipments", ['shipping_method_id' => $method->id, 'idempotency_key' => 'shipment-1'])
            ->assertCreated()->assertJsonPath('data.fee', 150)->assertJsonPath('data.status', 'pending');
        $this->actingAs($customer)->getJson("/api/v1/customer/orders/{$order->id}/shipments")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_shipment_creation_is_idempotent_and_foreign_order_is_hidden(): void
    {
        $this->seed(RbacSeeder::class);
        $customer = $this->userWithRole('customer');
        $other = User::factory()->create();
        $method = ShippingMethod::query()->create(['code' => 'express', 'name' => 'Express', 'base_fee' => 300, 'currency' => 'EGP', 'is_active' => true]);
        $order = CustomerOrder::query()->create(['user_id' => $customer->id, 'status' => 'pending', 'total_amount' => 1000, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);
        $foreign = CustomerOrder::query()->create(['user_id' => $other->id, 'status' => 'pending', 'total_amount' => 1000, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Giza']]);
        $payload = ['shipping_method_id' => $method->id, 'idempotency_key' => 'same-shipment'];

        $first = $this->actingAs($customer)->postJson("/api/v1/customer/orders/{$order->id}/shipments", $payload);
        $second = $this->actingAs($customer)->postJson("/api/v1/customer/orders/{$order->id}/shipments", $payload);
        $first->assertCreated();
        $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->actingAs($customer)->getJson("/api/v1/customer/orders/{$foreign->id}/shipments")->assertNotFound();
    }

    public function test_owner_can_manage_methods_and_update_shipment_status(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = $this->userWithRole('owner');
        $method = $this->actingAs($owner)->postJson('/api/v1/shipping-methods', ['code' => 'same-day', 'name' => 'Same Day', 'base_fee' => 500, 'currency' => 'EGP', 'is_active' => true])
            ->assertCreated()->json('data');
        $this->actingAs($owner)->patchJson('/api/v1/shipping-methods/' . $method['id'], ['name' => 'Same Day Updated'])
            ->assertOk()->assertJsonPath('data.name', 'Same Day Updated');
        $deletable = $this->actingAs($owner)->postJson('/api/v1/shipping-methods', ['code' => 'temporary', 'name' => 'Temporary', 'base_fee' => 50, 'currency' => 'EGP', 'is_active' => true])
            ->assertCreated()->json('data');

        $order = CustomerOrder::query()->create(['user_id' => $owner->id, 'status' => 'pending', 'total_amount' => 1000, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);
        $shipment = \App\Models\Shipment::query()->create(['order_id' => $order->id, 'user_id' => $owner->id, 'shipping_method_id' => $method['id'], 'method_code' => 'same-day', 'fee' => 500, 'currency' => 'EGP', 'status' => 'pending', 'address_snapshot' => ['city' => 'Cairo'], 'idempotency_key' => 'admin-shipment']);
        $this->actingAs($owner)->patchJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'picked_up'])
            ->assertOk()->assertJsonPath('data.status', 'picked_up');
        $this->actingAs($owner)->deleteJson('/api/v1/shipping-methods/' . $deletable['id'])->assertNoContent();
    }

    public function test_delivered_shipment_completes_shipped_order(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = $this->userWithRole('owner');
        $method = ShippingMethod::query()->create([
            'code' => 'delivery', 'name' => 'Delivery', 'base_fee' => 100,
            'currency' => 'EGP', 'is_active' => true,
        ]);
        $order = CustomerOrder::query()->create([
            'user_id' => $owner->id, 'status' => 'shipped',
            'total_amount' => 1000, 'currency' => 'EGP',
            'shipping_address' => ['city' => 'Cairo'],
        ]);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id, 'user_id' => $owner->id,
            'shipping_method_id' => $method->id, 'method_code' => $method->code,
            'fee' => 100, 'currency' => 'EGP', 'status' => 'out_for_delivery',
            'address_snapshot' => ['city' => 'Cairo'], 'idempotency_key' => 'delivery-order',
        ]);

        $this->actingAs($owner)->patchJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'delivered'])
            ->assertOk()->assertJsonPath('data.status', 'delivered');
        $this->assertDatabaseHas('customer_orders', ['id' => $order->id, 'status' => 'delivered']);
    }

    public function test_owner_can_generate_carrier_report_and_record_matching_settlement(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = $this->userWithRole('owner');
        $method = ShippingMethod::query()->create(['code' => 'bosta', 'name' => 'Bosta', 'carrier' => 'Bosta', 'base_fee' => 100, 'currency' => 'EGP', 'is_active' => true]);
        $order = CustomerOrder::query()->create(['user_id' => $owner->id, 'status' => 'delivered', 'total_amount' => 1000, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);
        Payment::query()->create(['order_id' => $order->id, 'user_id' => $owner->id, 'method' => 'cash_on_delivery', 'amount' => 1000, 'currency' => 'EGP', 'status' => 'succeeded', 'idempotency_key' => 'cod-settlement-1']);
        Shipment::query()->create(['order_id' => $order->id, 'user_id' => $owner->id, 'shipping_method_id' => $method->id, 'method_code' => 'bosta', 'fee' => 100, 'currency' => 'EGP', 'status' => 'delivered', 'address_snapshot' => ['city' => 'Cairo'], 'idempotency_key' => 'shipment-settlement-1']);

        $this->actingAs($owner)->getJson('/api/v1/shipping-reports?from=2026-01-01&to=2027-01-01&carrier=Bosta')
            ->assertOk()->assertJsonPath('data.carriers.0.gross_cod_amount', 1000)->assertJsonPath('data.carriers.0.expected_amount', 900);
        $this->actingAs($owner)->getJson('/api/v1/shipping-reconciliation?from=2026-01-01&to=2027-01-01&carrier=Bosta')
            ->assertOk()->assertJsonPath('data.carriers.0.collected_cod_amount', 1000)->assertJsonPath('data.carriers.0.pending_cod_amount', 0)->assertJsonPath('data.carriers.0.expected_amount', 900);
        $this->actingAs($owner)->postJson('/api/v1/shipping-settlements', [
            'carrier' => 'Bosta', 'period_start' => '2026-01-01', 'period_end' => '2027-01-01', 'currency' => 'EGP', 'paid_amount' => 900,
        ])->assertCreated()->assertJsonPath('data.status', 'settled')->assertJsonPath('data.difference', 0);
    }


    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());
        return $user;
    }
}
