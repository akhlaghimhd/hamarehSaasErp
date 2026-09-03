<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'              => ['sometimes', 'string', 'max:50'],
            'name'              => ['sometimes', 'string', 'max:200'],
            'account_type'      => ['sometimes', 'integer', 'in:1,2,3,4,5'],
            'parent_account_id' => ['nullable', 'uuid'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'is_active'         => ['sometimes', 'boolean'],
        ];
    }
}
