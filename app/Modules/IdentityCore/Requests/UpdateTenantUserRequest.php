<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name'  => ['sometimes', 'string', 'max:100'],
            'mobile'     => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_owner'   => ['sometimes', 'boolean'],
            'status'     => ['sometimes', 'integer', 'in:0,1'],
        ];
    }
}
