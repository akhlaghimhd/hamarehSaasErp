<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Accounting\DTOs\FinancialVoucherItemDTO;

class StoreFinancialVoucherItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'voucher_id' => ['required', 'uuid'],
            'account_id' => ['required', 'uuid'],
            'cost_center_id' => ['nullable', 'uuid'],
            'business_partner_id' => ['nullable', 'uuid'],
            'description' => ['required', 'string', 'max:1000'],
            'debit' => ['required', 'numeric', 'min:0'],
            'credit' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $debit = (float) $this->input('debit', 0);
            $credit = (float) $this->input('credit', 0);

            // در هر سطر استاندارد حسابداری، یا بدهکار مقدار دارد یا بستانکار (هر دو با هم نباید مقدار مثبت داشته باشند)
            if ($debit > 0 && $credit > 0) {
                $validator->errors()->add('debit_credit', 'A voucher item cannot have both debit and credit amounts greater than zero simultaneously.');
            }
            if ($debit == 0 && $credit == 0) {
                $validator->errors()->add('debit_credit', 'A voucher item must have either a debit or a credit amount.');
            }
        });
    }

    public function toDTO(): FinancialVoucherItemDTO
    {
        return new FinancialVoucherItemDTO(
            voucherId: $this->validated('voucher_id'),
            accountId: $this->validated('account_id'),
            costCenterId: $this->validated('cost_center_id'),
            businessPartnerId: $this->validated('business_partner_id'),
            description: $this->validated('description'),
            debit: (float) $this->validated('debit'),
            credit: (float) $this->validated('credit')
        );
    }
}