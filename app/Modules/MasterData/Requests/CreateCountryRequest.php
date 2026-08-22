<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCountryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'iso_code' => ['required', 'string', 'max:10'],
            'iso_numeric_code' => ['nullable', 'string', 'max:3'],
            'name' => ['required', 'string', 'max:200'],
            'phone_code' => ['nullable', 'string', 'max:20'],
            'status' => ['integer', 'in:1,2'],
        ];
    }
}