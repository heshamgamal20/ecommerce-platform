<?php

namespace App\Modules\Customer\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;

final class NotificationRequest extends FormRequest
{
    use AuthorizesRequest;

    public function authorize(): bool
    {
        return $this->authorizePermission('customer.notifications.view');
    }

    public function rules(): array
    {
        return ['unread' => ['sometimes', 'boolean'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
