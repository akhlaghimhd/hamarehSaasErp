<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerContactRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'first_name'   => ['nullable', 'string', 'max:100'],
            'last_name'    => ['nullable', 'string', 'max:100'],
            'role_title'   => ['nullable', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'is_primary'   => ['nullable', 'boolean'],
        ];
    }
}
