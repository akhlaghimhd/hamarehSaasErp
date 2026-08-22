<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'entity_type' => ['sometimes', 'string', 'max:100'],
            'entity_id' => ['sometimes', 'uuid'],
            'bank_name' => ['sometimes', 'string', 'max:100'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['sometimes', 'string', 'max:100'],
            'card_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}