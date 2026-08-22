<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Accounting\DTOs\AccountDTO;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'], // کد حساب در سطح شرکت باید یکتا باشد (در سرویس چک می‌شود)
            'name' => ['required', 'string', 'max:200'],
            'account_type' => ['required', 'integer', 'in:1,2,3,4,5'],
            'parent_account_id' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function toDTO(): AccountDTO
    {
        return new AccountDTO(
            code: $this->validated('code'),
            name: $this->validated('name'),
            accountType: (int) $this->validated('account_type'),
            parentAccountId: $this->validated('parent_account_id'),
            description: $this->validated('description'),
            isActive: $this->validated('is_active', true)
        );
    }
}