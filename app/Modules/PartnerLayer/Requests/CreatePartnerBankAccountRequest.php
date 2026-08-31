<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerBankAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'partner_id'     => ['required', 'uuid'],
            'bank_name'      => ['required', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'shaba_number'   => ['required', 'string', 'max:50'],
            'card_number'    => ['nullable', 'string', 'max:16'],
            'is_active'      => ['nullable', 'boolean'],
        ];
    }
}
