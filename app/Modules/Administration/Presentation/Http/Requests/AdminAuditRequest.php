<?php

namespace App\Modules\Administration\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;

final class AdminAuditRequest extends FormRequest
{
    use AuthorizesRequest;

    public function authorize(): bool
    {
        return $this->authorizePermission('audit.view');
    }

    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:120'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'target_type' => ['nullable', 'string', 'max:191'],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
