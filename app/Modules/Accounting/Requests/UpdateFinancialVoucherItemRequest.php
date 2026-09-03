<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialVoucherItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id'          => ['sometimes', 'uuid'],
            'cost_center_id'      => ['nullable', 'uuid'],
            'business_partner_id' => ['nullable', 'uuid'],
            'description'         => ['sometimes', 'string', 'max:1000'],
            'debit'               => ['sometimes', 'numeric', 'min:0'],
            'credit'              => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->has('debit') && !$this->has('credit')) {
                return;
            }
            $debit  = (float) $this->input('debit', 0);
            $credit = (float) $this->input('credit', 0);
            if ($debit > 0 && $credit > 0) {
                $validator->errors()->add('debit_credit', 'A voucher item cannot have both debit and credit amounts greater than zero simultaneously.');
            }
            if ($this->has('debit') && $this->has('credit') && $debit == 0 && $credit == 0) {
                $validator->errors()->add('debit_credit', 'A voucher item must have either a debit or a credit amount.');
            }
        });
    }
}
