<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerRequest extends FormRequest
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
            'partner_type'        => ['nullable', 'integer', 'in:1,2,3,4'],
            'ownership_type'      => ['nullable', 'integer', 'in:1,2'],
            'commission_enabled'  => ['nullable', 'boolean'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'email'               => ['nullable', 'email', 'max:150'],
            'address'             => ['nullable', 'string'],
            'status'              => ['nullable', 'integer'],
            'parent_partner_id'   => ['nullable', 'uuid'],
        ];
    }
}
