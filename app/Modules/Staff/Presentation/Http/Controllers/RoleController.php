<?php

namespace App\Modules\Staff\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Staff\Domain\Exceptions\StaffActionNotAllowedException;
use App\Modules\Staff\Presentation\Http\Requests\RoleManagementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RoleController extends Controller
{
    public function index(RoleManagementRequest $request): JsonResponse
    {
        return response()->json(['data' => Role::query()->with('permissions:id,name,slug,group')->withCount('users')->orderBy('is_system', 'desc')->orderBy('name')->paginate(50)]);
    }

    public function store(RoleManagementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = DB::transaction(function () use ($data, $request): Role {
            $role = Role::query()->create(['name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?? null, 'is_system' => false, 'is_active' => true]);
            $role->permissions()->sync(Permission::query()->whereIn('slug', $data['permissions'] ?? [])->pluck('id'));
            AuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'role.created', 'target_type' => Role::class, 'target_id' => $role->id, 'metadata' => ['permissions' => $data['permissions'] ?? []]]);

            return $role->load('permissions');
        });

        return response()->json(['data' => $role], 201);
    }

    public function update(RoleManagementRequest $request, int $role): JsonResponse
    {
        $data = $request->validated();
        $item = DB::transaction(function () use ($data, $request, $role): Role {
            $item = Role::query()->findOrFail($role);
            if ($item->is_system) {
                throw new StaffActionNotAllowedException('System roles cannot be modified.');
            }
            $item->update(array_filter(['name' => $data['name'] ?? null, 'description' => $data['description'] ?? null, 'is_active' => $data['is_active'] ?? null], fn ($value) => $value !== null));
            if (array_key_exists('permissions', $data)) {
                $item->permissions()->sync(Permission::query()->whereIn('slug', $data['permissions'])->pluck('id'));
            }
            AuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'role.updated', 'target_type' => Role::class, 'target_id' => $item->id, 'metadata' => ['permissions' => $data['permissions'] ?? null]]);

            return $item->fresh('permissions');
        });

        return response()->json(['data' => $item]);
    }

    public function destroy(RoleManagementRequest $request, int $role): JsonResponse
    {
        $item = Role::query()->findOrFail($role);
        if ($item->is_system) {
            throw new StaffActionNotAllowedException('System roles cannot be deleted.');
        }
        if ($item->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'Role is assigned to users and cannot be deleted.']);
        }
        $item->delete();
        AuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'role.deleted', 'target_type' => Role::class, 'target_id' => $role]);

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    public function effectivePermissions(RoleManagementRequest $request, int $staffId): JsonResponse
    {
        $user = User::query()->with(['roles.permissions', 'permissionOverrides'])->whereDoesntHave('roles', fn ($query) => $query->where('slug', 'customer'))->findOrFail($staffId);
        $rolePermissions = $user->roles->where('is_active', true)->flatMap->permissions->pluck('slug')->unique()->values();
        $overrides = $user->permissionOverrides->mapWithKeys(fn ($permission) => [$permission->slug => (bool) $permission->pivot->allowed]);
        $effective = $rolePermissions->filter(fn ($slug) => ! $overrides->has($slug) || $overrides->get($slug))->merge($overrides->filter(fn ($allowed) => $allowed)->keys())->unique()->values();

        return response()->json(['data' => ['user_id' => $user->id, 'roles' => $user->roles->pluck('slug'), 'role_permissions' => $rolePermissions, 'overrides' => $overrides, 'effective_permissions' => $effective]]);
    }

    public function overrides(RoleManagementRequest $request, int $staffId): JsonResponse
    {
        $user = User::query()->with('roles')->whereDoesntHave('roles', fn ($query) => $query->where('slug', 'customer'))->findOrFail($staffId);
        if ($user->hasRole('owner')) {
            throw new StaffActionNotAllowedException('Owner permissions cannot be overridden.');
        }
        $permissions = Permission::query()->whereIn('slug', collect($request->validated('permissions'))->pluck('slug'))->get()->keyBy('slug');
        foreach ($request->validated('permissions') as $override) {
            $user->permissionOverrides()->syncWithoutDetaching([$permissions[$override['slug']]->id => ['allowed' => $override['allowed']]]);
        }
        AuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'staff.permission_overrides.updated', 'target_type' => User::class, 'target_id' => $user->id, 'metadata' => ['permissions' => $request->validated('permissions')]]);

        return $this->effectivePermissions($request, $staffId);
    }

    public function revokeSessions(RoleManagementRequest $request, int $staffId): JsonResponse
    {
        $user = User::query()->with('roles')->whereDoesntHave('roles', fn ($query) => $query->where('slug', 'customer'))->findOrFail($staffId);
        if ($user->hasRole('owner')) {
            throw new StaffActionNotAllowedException('Owner sessions cannot be revoked through staff management.');
        }
        $count = DB::table('sessions')->where('user_id', $user->id)->delete();
        AuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'staff.sessions.revoked', 'target_type' => User::class, 'target_id' => $user->id, 'metadata' => ['count' => $count]]);

        return response()->json(['data' => ['revoked_sessions' => $count]]);
    }
}
