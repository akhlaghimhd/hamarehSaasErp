<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date'        => ['sometimes', 'date'],
            'tax_type'                => ['sometimes', 'integer', 'in:1,2,3'],
            'base_amount'             => ['sometimes', 'numeric', 'min:0'],
            'tax_amount'              => ['sometimes', 'numeric', 'min:0'],
            'tax_rate'                => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'reference_document_type' => ['nullable', 'string', 'max:50'],
            'reference_document_id'   => ['nullable', 'uuid'],
            'business_partner_id'     => ['nullable', 'uuid'],
        ];
    }
}
