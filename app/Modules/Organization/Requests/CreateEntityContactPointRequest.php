<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEntityContactPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_type'         => ['required', 'string', 'max:100', 'in:COMPANY,BRANCH,BUSINESS_PARTNER,DEPARTMENT'],
            'entity_id'           => ['required', 'uuid'],
            'contact_point_type'  => ['required', 'integer', 'in:1,2,3,4'],
            'contact_value'       => ['required', 'string', 'max:255'],
            'is_primary'          => ['boolean'],
            'status'              => ['nullable', 'integer', 'min:1', 'max:9'],
        ];
    }
}
