<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id'         => ['required', 'uuid'],
            'payout_number'      => ['required', 'string', 'max:100'],
            'total_amount'       => ['required', 'numeric', 'min:0'],
            'currency_id'        => ['required', 'uuid'],
            'bank_account_id'    => ['nullable', 'uuid'],
            'payout_date'        => ['nullable', 'date'],
            'payment_reference'  => ['nullable', 'string', 'max:200'],
            'status'             => ['nullable', 'integer', 'in:1,2,3'],
            'description'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
