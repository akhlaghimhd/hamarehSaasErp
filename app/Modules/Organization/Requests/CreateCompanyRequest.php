<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                      => ['required', 'string', 'max:50'],
            'name'                      => ['required', 'string', 'max:200'],
            'legal_name'                => ['nullable', 'string', 'max:200'],
            'trade_name'                => ['nullable', 'string', 'max:200'],
            'company_type'              => ['nullable', 'integer', 'min:1', 'max:99'],
            'registration_number'       => ['nullable', 'string', 'max:100'],
            'registration_date'         => ['nullable', 'date'],
            'registration_place'        => ['nullable', 'string', 'max:200'],
            'incorporation_country_id'  => ['nullable', 'uuid'],
            'economic_code'             => ['nullable', 'string', 'max:100'],
            'tax_identifier'            => ['nullable', 'string', 'max:100'],
            'national_id'               => ['nullable', 'string', 'max:50'],
            'vat_registration'          => ['nullable', 'string', 'max:100'],
            'is_active'                 => ['boolean'],
            'status'                    => ['nullable', 'integer', 'in:1,2,3,4'],
        ];
    }
}
