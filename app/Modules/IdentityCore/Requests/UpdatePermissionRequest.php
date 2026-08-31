<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:200'],
            'module_name' => ['sometimes', 'string', 'max:100'],
            'action_type' => ['sometimes', 'nullable', 'string', 'max:50', 'in:CREATE,READ,UPDATE,DELETE,APPROVE,EXECUTE'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status'      => ['sometimes', 'integer', 'in:0,1'],
        ];
    }
}
