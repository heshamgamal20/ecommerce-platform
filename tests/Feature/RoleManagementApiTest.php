<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RoleManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_role_update_permissions_and_view_effective_permissions(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = $this->userWithRole('owner');
        $staff = $this->userWithRole('assistant');
        $role = $this->actingAs($owner)->postJson('/api/v1/roles', ['name' => 'Returns Reviewer', 'slug' => 'returns_reviewer', 'permissions' => ['orders.view']])->assertCreated()->json('data');
        $this->actingAs($owner)->putJson('/api/v1/roles/'.$role['id'], ['permissions' => ['orders.view', 'shipping.view']])->assertOk()->assertJsonPath('data.permissions.1.slug', 'shipping.view');
        $staff->roles()->sync([$role['id']]);
        $this->actingAs($owner)->getJson('/api/v1/staff/'.$staff->id.'/effective-permissions')->assertOk()->assertJsonPath('data.effective_permissions.0', 'orders.view')->assertJsonFragment(['shipping.view']);
    }

    public function test_owner_role_cannot_be_modified_or_overridden(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = $this->userWithRole('owner');
        $ownerRole = Role::query()->where('slug', 'owner')->firstOrFail();
        $this->actingAs($owner)->putJson('/api/v1/roles/'.$ownerRole->id, ['name' => 'Changed'])->assertForbidden();
        $permission = Permission::query()->where('slug', 'orders.view')->firstOrFail();
        $this->actingAs($owner)->putJson('/api/v1/staff/'.$owner->id.'/permission-overrides', ['permissions' => [['slug' => $permission->slug, 'allowed' => false]]])->assertForbidden();
    }

    public function test_owner_can_revoke_all_sessions_for_non_owner_staff(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = $this->userWithRole('owner');
        $staff = $this->userWithRole('assistant');
        DB::table('sessions')->insert([
            ['id' => 'session-a', 'user_id' => $staff->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'payload', 'last_activity' => now()->timestamp],
            ['id' => 'session-b', 'user_id' => $staff->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'payload', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($owner)->postJson('/api/v1/staff/'.$staff->id.'/revoke-sessions')->assertOk()->assertJsonPath('data.revoked_sessions', 2);
        $this->assertDatabaseMissing('sessions', ['user_id' => $staff->id]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
