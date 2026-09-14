<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_filter_audit_logs_and_manage_own_notifications(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = $this->userWithRole('owner');
        $manager = $this->userWithRole('manager');
        AuditLog::query()->create(['actor_id' => $owner->id, 'action' => 'order.updated', 'target_type' => 'Order', 'target_id' => 7, 'metadata' => ['status' => 'confirmed']]);
        $notification = AdminNotification::query()->create(['user_id' => $owner->id, 'type' => 'payment.failed', 'title' => 'Payment failed', 'body' => 'Review payment 7', 'data' => ['payment_id' => 7]]);

        $this->actingAs($manager)->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/v1/admin/audit-logs?action=order.updated&target_id=7')->assertOk()->assertJsonPath('data.data.0.action', 'order.updated');
        $this->actingAs($owner)->getJson('/api/v1/admin/notifications?unread_only=1')->assertOk()->assertJsonPath('unread_count', 1);
        $this->actingAs($owner)->patchJson('/api/v1/admin/notifications/'.$notification->id.'/read')->assertOk()->assertJsonPath('data.id', $notification->id);
        $this->actingAs($owner)->patchJson('/api/v1/admin/notifications/read-all')->assertOk()->assertJsonPath('data.marked_read', 0);
    }

    public function test_owner_can_export_sales_and_payments_as_csv(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner)->get('/api/v1/reports/exports/sales.csv?from=2026-01-01&to=2026-12-31')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertSee('id,user_id,status');
        $this->actingAs($owner)->get('/api/v1/reports/exports/payments.csv?from=2026-01-01&to=2026-12-31')->assertOk()->assertSee('id,order_id,user_id');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());
        return $user;
    }
}
