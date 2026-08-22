<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'iso_code' => ['sometimes', 'string', 'max:10'],
            'iso_numeric_code' => ['nullable', 'string', 'max:3'],
            'name' => ['sometimes', 'string', 'max:200'],
            'phone_code' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}