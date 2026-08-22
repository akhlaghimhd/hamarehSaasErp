<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEntityAddressRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'entity_type' => ['sometimes', 'string', 'max:100'],
            'entity_id' => ['sometimes', 'uuid'],
            'address_type_id' => ['sometimes', 'uuid'],
            'country_id' => ['sometimes', 'uuid'],
            'province_id' => ['nullable', 'uuid'],
            'city_id' => ['nullable', 'uuid'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'address_text' => ['sometimes', 'string'],
            'is_primary' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}