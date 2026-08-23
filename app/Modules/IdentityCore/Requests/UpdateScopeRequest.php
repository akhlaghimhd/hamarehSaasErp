<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope_name'   => ['sometimes', 'string', 'max:150'],
            'scope_type'   => ['sometimes', 'string', 'max:50', 'in:COMPANY,BRANCH,WAREHOUSE,DEPARTMENT,COST_CENTER,CUSTOM'],
            'reference_id' => ['nullable', 'uuid'],
            'description'  => ['nullable', 'string', 'max:500'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }
}