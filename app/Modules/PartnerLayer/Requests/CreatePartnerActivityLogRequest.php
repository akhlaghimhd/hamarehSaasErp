<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerActivityLogRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'partner_id'  => ['required', 'uuid'],
            'user_id'     => ['required', 'uuid'],
            'action_type' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'ip_address'  => ['required', 'string', 'max:45'],
        ];
    }
}
