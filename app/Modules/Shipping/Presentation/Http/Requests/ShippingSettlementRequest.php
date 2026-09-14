<?php

namespace App\Modules\Shipping\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;

final class ShippingSettlementRequest extends FormRequest
{
    use AuthorizesRequest;

    public function authorize(): bool
    {
        return $this->authorizePermission(in_array($this->route()?->getName(), ['shipping-reports.index', 'shipping-reconciliation.index', 'shipping-settlements.index'], true) ? 'shipping.reports.view' : 'shipping.settlements.manage');
    }

    public function rules(): array
    {
        if (in_array($this->route()?->getName(), ['shipping-reports.index', 'shipping-reconciliation.index'], true)) {
            return [
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
                'carrier' => ['nullable', 'string', 'max:191'],
            ];
        }

        if ($this->route()?->getName() === 'shipping-settlements.index') {
            return ['carrier' => ['nullable', 'string', 'max:191'], 'status' => ['nullable', 'in:open,settled,disputed,approved'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
        }

        if ($this->route()?->getName() === 'shipping-settlements.statement') {
            return ['statement' => ['required', 'file', 'mimes:csv,txt', 'max:10240']];
        }
        if ($this->route()?->getName() === 'shipping-settlements.approve') {
            return [];
        }

        return [
            'carrier' => ['required', 'string', 'max:191'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'paid_amount' => ['required', 'integer', 'min:0'],
            'other_adjustments' => ['sometimes', 'integer'],
            'provider_reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
