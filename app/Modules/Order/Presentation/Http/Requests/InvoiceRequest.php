<?php
namespace App\Modules\Order\Presentation\Http\Requests;
use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;
final class InvoiceRequest extends FormRequest
{
    use AuthorizesRequest;
    public function authorize(): bool { return $this->authorizePermission($this->route()?->getName() === 'admin.invoices.credit-notes.store' ? 'orders.return' : 'orders.edit'); }
    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'admin.invoices.credit-notes.store' => ['return_id'=>['nullable','integer','exists:order_returns,id'],'amount'=>['required','integer','min:1'],'reason'=>['nullable','string','max:255']],
            default => [],
        };
    }
}
