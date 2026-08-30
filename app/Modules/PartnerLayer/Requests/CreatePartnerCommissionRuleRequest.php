<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agreement_id'      => ['required', 'uuid'],
            'revenue_type'      => ['required', 'integer'],
            'commission_type'   => ['required', 'integer'],
            'commission_value'  => ['required', 'numeric'],
            'calculation_basis' => ['nullable', 'integer'],
            'minimum_amount'    => ['nullable', 'numeric'],
            'maximum_amount'    => ['nullable', 'numeric'],
            'effective_from'    => ['nullable', 'date'],
            'effective_to'      => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status'            => ['nullable', 'integer', 'in:1,2'],
        ];
    }
}
