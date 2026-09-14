<?php

namespace App\Modules\Catalog\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Concerns\AuthorizesRequest;
use Illuminate\Foundation\Http\FormRequest;

final class PurchasePriceRequest extends FormRequest
{
    use AuthorizesRequest;

    public function authorize(): bool
    {
        return $this->authenticatedUser()->hasRole('owner');
    }

    public function rules(): array
    {
        return [];
    }
}
