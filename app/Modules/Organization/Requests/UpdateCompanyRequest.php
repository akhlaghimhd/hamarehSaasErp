<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                => ['required', 'string', 'max:50'],
            'name'                => ['required', 'string', 'max:200'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'economic_code'       => ['nullable', 'string', 'max:100'],
            'is_active'           => ['boolean'],
        ];
    }
}