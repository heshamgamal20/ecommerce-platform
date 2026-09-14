<?php

namespace App\Modules\Order\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;

final class AdminReturnRequest extends FormRequest
{
    use AuthorizesRequest;

    public function authorize(): bool
    {
        return $this->authorizePermission($this->isMethod('get') ? 'orders.view' : 'orders.return');
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'admin.returns.index' => ['q' => ['nullable', 'string', 'max:191'], 'status' => ['nullable', 'in:pending,approved,rejected'], 'inspection_status' => ['nullable', 'string', 'in:pending,passed,failed,partial'], 'reason' => ['nullable', 'string', 'max:120'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']],
            'admin.returns.receive' => ['notes' => ['nullable', 'string', 'max:2000']],
            'admin.returns.inspect' => ['inspection_status' => ['required', 'in:passed,failed,partial'], 'inspection_notes' => ['nullable', 'string', 'max:2000'], 'final_refund_amount' => ['required', 'integer', 'min:0'], 'payment_id' => ['nullable', 'integer', 'exists:payments,id']],
            default => [],
        };
    }
}
