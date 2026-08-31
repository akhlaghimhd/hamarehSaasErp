<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payout_number'     => ['nullable', 'string', 'max:100'],
            'total_amount'      => ['nullable', 'numeric', 'min:0'],
            'currency_id'       => ['nullable', 'uuid'],
            'bank_account_id'   => ['nullable', 'uuid'],
            'payout_date'       => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:200'],
            'status'            => ['nullable', 'integer', 'in:1,2,3'],
            'description'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
