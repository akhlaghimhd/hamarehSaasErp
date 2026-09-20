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
            'first_name'       => ['sometimes', 'string', 'max:100'],
            'last_name'        => ['sometimes', 'string', 'max:100'],
            'mobile'           => ['sometimes', 'nullable', 'string', 'max:20'],
            'email_local_part' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9._-]*[a-zA-Z0-9]$|^[a-zA-Z0-9]$/',
            ],
            'is_owner'         => ['sometimes', 'boolean'],
            'status'           => ['sometimes', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'email_local_part.regex' => 'بخش ابتدایی ایمیل فقط شامل حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط باشد.',
        ];
    }
}
