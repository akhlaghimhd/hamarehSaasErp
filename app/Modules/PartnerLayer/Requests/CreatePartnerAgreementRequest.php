<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id'         => ['required', 'uuid'],
            'agreement_number'   => ['required', 'string', 'max:50'],
            'agreement_type'     => ['nullable', 'integer'],
            'start_date'         => ['nullable', 'date'],
            'end_date'           => ['nullable', 'date', 'after_or_equal:start_date'],
            'payment_cycle'      => ['nullable', 'integer'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'status'             => ['nullable', 'integer', 'in:1,2'],
        ];
    }
}
