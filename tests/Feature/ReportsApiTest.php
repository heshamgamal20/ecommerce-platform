<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_read_operational_reports(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::query()->where('slug', 'owner')->firstOrFail());
        $method = ShippingMethod::query()->create(['code' => 'bosta', 'name' => 'Bosta', 'carrier' => 'Bosta', 'base_fee' => 100, 'currency' => 'EGP', 'is_active' => true]);
        $order = CustomerOrder::query()->create(['user_id' => $owner->id, 'status' => 'delivered', 'subtotal_amount' => 1000, 'total_amount' => 1100, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);
        Payment::query()->create(['order_id' => $order->id, 'user_id' => $owner->id, 'method' => 'cash_on_delivery', 'amount' => 1100, 'currency' => 'EGP', 'status' => 'paid', 'idempotency_key' => 'report-payment']);
        Shipment::query()->create(['order_id' => $order->id, 'user_id' => $owner->id, 'shipping_method_id' => $method->id, 'method_code' => 'bosta', 'fee' => 100, 'currency' => 'EGP', 'status' => 'delivered', 'address_snapshot' => ['city' => 'Cairo'], 'idempotency_key' => 'report-shipment']);
        OrderReturn::query()->create(['order_id' => $order->id, 'user_id' => $owner->id, 'status' => 'pending', 'reason' => 'size']);

        $query = '?from=2026-01-01&to=2027-01-01';
        $this->actingAs($owner)->getJson('/api/v1/reports/sales'.$query)->assertOk()->assertJsonPath('data.orders', 1);
        $this->actingAs($owner)->getJson('/api/v1/reports/payments'.$query)->assertOk()->assertJsonPath('data.count', 1);
        $this->actingAs($owner)->getJson('/api/v1/reports/returns'.$query)->assertOk()->assertJsonPath('data.count', 1);
        $this->actingAs($owner)->getJson('/api/v1/reports/carriers/performance'.$query)->assertOk()->assertJsonPath('data.carriers.0.carrier', 'Bosta');
    }
}
