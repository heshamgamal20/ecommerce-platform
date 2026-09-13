<?php

namespace App\Modules\Order\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;

final class OrderRequest extends FormRequest
{
    use AuthorizesRequest;

    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $permission = match ($this->route()?->getName()) {
            'orders.index', 'orders.show' => 'orders.view',
            'customer.orders.index', 'customer.orders.show' => 'customer.orders.view',
            'customer.orders.cancel' => 'customer.orders.manage',
            'orders.status' => 'orders.edit',
            'orders.cancel' => 'orders.cancel',
            default => 'orders.view',
        };

        return $this->authorizePermission($permission);
    }

    public function rules(): array
    {
        if (in_array($this->route()?->getName(), ['orders.index', 'customer.orders.index'], true)) {
            return ['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
        }
        if ($this->route()?->getName() !== 'orders.status') {
            return [];
        }

        return [
            'status' => ['required', 'string', 'in:pending,confirmed,processing,shipped,delivered,cancelled,refunded'],
        ];
    }
}
