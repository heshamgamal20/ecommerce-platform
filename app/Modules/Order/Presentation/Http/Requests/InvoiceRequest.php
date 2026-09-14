<?php

namespace App\Modules\Order\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;

final class InvoiceRequest extends FormRequest
{
    use AuthorizesRequest;

    public function authorize(): bool
    {
        $permission = match ($this->route()?->getName()) {
            'admin.invoices.show', 'admin.invoices.print' => 'orders.view',
            'admin.invoices.issue' => 'orders.edit',
            'admin.invoices.cancel' => 'orders.cancel',
            'admin.invoices.credit-notes.store' => 'orders.return',
            default => '__invalid_invoice_route__',
        };

        return $this->authorizePermission($permission);
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'admin.invoices.credit-notes.store' => [
                'return_id' => ['nullable', 'integer', 'exists:order_returns,id'],
                'amount' => ['required', 'integer', 'min:1'],
                'reason' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }
}
