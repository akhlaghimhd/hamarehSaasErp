<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEntityAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_type'     => ['required', 'string', 'max:100'],
            'entity_id'       => ['required', 'uuid'],
            // Optional: company UI may not have address_type/country catalogs yet
            'address_type_id' => ['nullable', 'uuid'],
            'country_id'      => ['nullable', 'uuid'],
            'province_id'     => ['nullable', 'uuid'],
            'city_id'         => ['nullable', 'uuid'],
            'postal_code'    => ['nullable', 'string', 'max:50'],
            'address_text'    => ['required', 'string'],
            'is_primary'      => ['boolean'],
            'status'          => ['integer', 'in:1,2'],
        ];
    }
}
