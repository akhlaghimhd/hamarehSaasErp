<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBankAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'max:100'],
            'entity_id' => ['required', 'uuid'],
            'bank_name' => ['required', 'string', 'max:100'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:100'],
            'card_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['boolean'],
            'status' => ['integer', 'in:1,2'],
        ];
    }
}