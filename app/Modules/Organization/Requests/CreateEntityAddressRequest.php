<?php

namespace App\Modules\Organization\Requests;

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
            'entity_type'     => ['required', 'string', 'max:100', 'in:COMPANY,BRANCH,BUSINESS_PARTNER,DEPARTMENT'],
            'entity_id'       => ['required', 'uuid'],
            'address_text'    => ['required', 'string'],
            'address_type_id' => ['nullable', 'uuid'],
            'country_id'      => ['nullable', 'uuid'],
            'province_name'   => ['nullable', 'string', 'max:150'],
            'city_name'       => ['nullable', 'string', 'max:150'],
            'postal_code'    => ['nullable', 'string', 'max:50'],
            'is_primary'      => ['boolean'],
            'status'          => ['nullable', 'integer', 'min:1', 'max:9'],
        ];
    }
}
