<?php

namespace App\Modules\Shipping\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;

final class AdminShipmentRequest extends FormRequest
{
    use AuthorizesRequest;

    public function authorize(): bool
    {
        return $this->authorizePermission('shipping.view');
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'string', 'max:30'],
            'carrier' => ['nullable', 'string', 'max:191'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'older_than_hours' => ['sometimes', 'integer', 'min:1', 'max:8760'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
