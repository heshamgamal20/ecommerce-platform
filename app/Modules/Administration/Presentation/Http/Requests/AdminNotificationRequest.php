<?php

namespace App\Modules\Administration\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;

final class AdminNotificationRequest extends FormRequest
{
    use AuthorizesRequest;

    public function authorize(): bool
    {
        return $this->authorizePermission('admin.notifications.view');
    }

    public function rules(): array
    {
        return [
            'unread_only' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
