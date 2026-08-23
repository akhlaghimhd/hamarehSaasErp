<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_user_id' => ['required', 'uuid'],
            'scope_ids'      => ['required', 'array', 'min:1'],
            'scope_ids.*'    => ['uuid'],
        ];
    }
}