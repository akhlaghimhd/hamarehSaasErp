<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculatePartnerCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id'         => ['required', 'uuid'],
            'tenant_id'          => ['required', 'uuid'],
            'commission_rule_id' => ['required', 'uuid'],
            'base_amount'        => ['required', 'numeric', 'min:0'],
            'currency_id'        => ['required', 'uuid'],
            'invoice_id'         => ['nullable', 'uuid'],
            'exchange_rate'      => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
