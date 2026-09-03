<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_date'     => ['sometimes', 'date'],
            'description'      => ['sometimes', 'string', 'max:1000'],
            'total_amount'     => ['sometimes', 'numeric', 'min:0'],
            'reference_number' => ['sometimes', 'string', 'max:100'],
            'currency_id'      => ['nullable', 'uuid'],
        ];
    }
}
