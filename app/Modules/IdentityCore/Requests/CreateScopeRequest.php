<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope_name'   => ['required', 'string', 'max:150'],
            'scope_type'   => ['required', 'string', 'max:50', 'in:COMPANY,BRANCH,WAREHOUSE,DEPARTMENT,COST_CENTER,CUSTOM'],
            'reference_id' => ['nullable', 'uuid'],
            'description'  => ['nullable', 'string', 'max:500'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }
}