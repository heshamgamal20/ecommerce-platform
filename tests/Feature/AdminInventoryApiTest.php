<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminInventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_manager_can_filter_low_stock_and_view_movements(): void
    {
        $this->seed(RbacSeeder::class);
        $manager = $this->userWithRole('product_manager');
        $product = Product::query()->create(['name' => 'Low Stock Product', 'slug' => 'low-stock-product', 'type' => 'simple', 'status' => 'active', 'price' => 300]);
        $item = InventoryItem::query()->create(['product_id' => $product->id, 'on_hand' => 4, 'reserved' => 1]);
        InventoryMovement::query()->create(['inventory_item_id' => $item->id, 'actor_id' => $manager->id, 'quantity' => 4, 'on_hand_after' => 4, 'reason' => 'purchase', 'note' => 'Initial stock']);

        $this->actingAs($manager)->getJson('/api/v1/admin/inventory?low_stock=1&threshold=5')->assertOk()->assertJsonPath('data.data.0.id', $item->id)->assertJsonPath('filters.threshold', 5);
        $this->actingAs($manager)->getJson('/api/v1/admin/inventory/'.$item->id)->assertOk()->assertJsonPath('data.available', 3);
        $this->actingAs($manager)->getJson('/api/v1/admin/inventory/'.$item->id.'/movements?reason=purchase')->assertOk()->assertJsonPath('data.data.0.reason', 'purchase');
    }

    public function test_customer_cannot_access_inventory_directory(): void
    {
        $this->seed(RbacSeeder::class);
        $customer = $this->userWithRole('customer');
        $this->actingAs($customer)->getJson('/api/v1/admin/inventory')->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());
        return $user;
    }
}
