<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminReturnApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_receive_and_inspect_an_approved_return(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = $this->userWithRole('admin');
        $customer = $this->userWithRole('customer');
        $product = Product::query()->create(['name' => 'Return Product', 'slug' => 'return-product', 'type' => 'simple', 'status' => 'active', 'price' => 500]);
        $order = CustomerOrder::query()->create(['user_id' => $customer->id, 'status' => 'delivered', 'total_amount' => 500, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);
        $orderItem = $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'unit_price' => 500, 'total_amount' => 500]);
        $return = OrderReturn::query()->create(['order_id' => $order->id, 'user_id' => $customer->id, 'status' => 'approved', 'reason' => 'Damaged', 'refund_amount' => 500]);
        $return->items()->create(['order_item_id' => $orderItem->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 500]);

        $this->actingAs($admin)->getJson('/api/v1/admin/returns?status=approved')->assertOk()->assertJsonPath('data.data.0.id', $return->id);
        $this->actingAs($admin)->postJson('/api/v1/admin/returns/'.$return->id.'/receive', ['notes' => 'Package received'])->assertOk()->assertJsonPath('data.received_by', $admin->id);
        $this->actingAs($admin)->postJson('/api/v1/admin/returns/'.$return->id.'/inspect', ['inspection_status' => 'passed', 'inspection_notes' => 'Good condition', 'final_refund_amount' => 450])->assertOk()->assertJsonPath('data.inspection_status', 'passed')->assertJsonPath('data.final_refund_amount', 450);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.return.received', 'target_id' => $return->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.return.inspected', 'target_id' => $return->id]);
    }

    public function test_customer_cannot_access_admin_returns(): void
    {
        $this->seed(RbacSeeder::class);
        $customer = $this->userWithRole('customer');
        $this->actingAs($customer)->getJson('/api/v1/admin/returns')->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());
        return $user;
    }
}
