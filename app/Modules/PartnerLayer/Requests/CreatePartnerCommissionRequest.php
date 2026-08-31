<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id'                 => ['required', 'uuid'],
            'tenant_id'                  => ['required', 'uuid'],
            'invoice_id'                 => ['nullable', 'uuid'],
            'commission_rule_id'         => ['nullable', 'uuid'],
            'base_amount'                => ['required', 'numeric', 'min:0'],
            'commission_type_snapshot'   => ['required', 'integer'],
            'commission_value_snapshot'  => ['required', 'numeric'],
            'commission_amount'          => ['required', 'numeric', 'min:0'],
            'currency_id'                => ['required', 'uuid'],
            'exchange_rate'              => ['nullable', 'numeric', 'min:0'],
            'status'                     => ['nullable', 'integer', 'in:1,2,3'],
            'calculated_at'              => ['nullable', 'date'],
            'paid_at'                    => ['nullable', 'date'],
        ];
    }
}
