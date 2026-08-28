<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                 => ['required', 'string', 'max:50'],
            'name'                 => ['required', 'string', 'max:200'],
            'parent_department_id' => ['nullable', 'uuid'],
            'manager_user_id'      => ['nullable', 'uuid'],
            'is_active'            => ['boolean'],
        ];
    }
}