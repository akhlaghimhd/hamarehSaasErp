<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id' => ['required', 'uuid'],
            'user_id'    => ['required', 'uuid'],
            'is_primary' => ['nullable', 'boolean'],
            'status'     => ['nullable', 'integer', 'in:1,2'],
        ];
    }
}
