<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'revenue_type'      => ['nullable', 'integer'],
            'commission_type'   => ['nullable', 'integer'],
            'commission_value'  => ['nullable', 'numeric'],
            'calculation_basis' => ['nullable', 'integer'],
            'minimum_amount'    => ['nullable', 'numeric'],
            'maximum_amount'    => ['nullable', 'numeric'],
            'effective_from'    => ['nullable', 'date'],
            'effective_to'      => ['nullable', 'date'],
            'status'            => ['nullable', 'integer', 'in:1,2'],
        ];
    }
}
