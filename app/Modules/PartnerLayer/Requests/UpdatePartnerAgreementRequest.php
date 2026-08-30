<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agreement_number' => ['nullable', 'string', 'max:50'],
            'agreement_type'   => ['nullable', 'integer'],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date'],
            'payment_cycle'    => ['nullable', 'integer'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'status'           => ['nullable', 'integer', 'in:1,2'],
        ];
    }
}
