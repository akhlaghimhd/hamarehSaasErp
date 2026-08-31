<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerBankAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'bank_name'      => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'shaba_number'   => ['nullable', 'string', 'max:50'],
            'card_number'    => ['nullable', 'string', 'max:16'],
            'is_active'      => ['nullable', 'boolean'],
        ];
    }
}
