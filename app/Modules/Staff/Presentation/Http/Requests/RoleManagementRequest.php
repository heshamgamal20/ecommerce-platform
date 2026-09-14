<?php

namespace App\Modules\Staff\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;

final class RoleManagementRequest extends FormRequest
{
    use AuthorizesRequest;

    public function authorize(): bool
    {
        $permission = match ($this->route()?->getName()) {
            'roles.index', 'staff.permissions' => 'roles.view',
            'roles.store' => 'roles.create',
            'roles.update' => 'roles.update',
            'roles.destroy' => 'roles.delete',
            default => 'permissions.manage',
        };

        return $this->authorizePermission($permission);
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'roles.store' => ['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash', 'max:100', 'unique:roles,slug'], 'description' => ['nullable', 'string', 'max:1000'], 'permissions' => ['sometimes', 'array'], 'permissions.*' => ['string', 'exists:permissions,slug']],
            'roles.update' => ['name' => ['sometimes', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'permissions' => ['sometimes', 'array'], 'permissions.*' => ['string', 'exists:permissions,slug'], 'is_active' => ['sometimes', 'boolean']],
            'staff.permissions' => [],
            'staff.permission-overrides' => ['permissions' => ['required', 'array'], 'permissions.*.slug' => ['required', 'string', 'exists:permissions,slug'], 'permissions.*.allowed' => ['required', 'boolean']],
            default => [],
        };
    }
}
