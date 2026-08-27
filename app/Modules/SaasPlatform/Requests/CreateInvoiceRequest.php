<?php

namespace App\Modules\SaasPlatform\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id'          => ['required', 'uuid'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.item_type'  => ['required', 'string', 'max:50'],
            'items.*.description'=> ['nullable', 'string', 'max:500'],
            'items.*.amount'     => ['required', 'numeric', 'min:0'],
            'items.*.reference_id'=> ['nullable', 'uuid'],
            'discount_amount'    => ['nullable', 'numeric', 'min:0'],
            'tax_amount'         => ['nullable', 'numeric', 'min:0'],
            'due_date'           => ['nullable', 'date'],
        ];
    }
}