<?php

namespace App\Modules\ProjectManagement\Requests\ProjectMember;

use Illuminate\Foundation\Http\FormRequest;

class AddProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id'   => ['required', 'uuid', 'exists:projects,project_id'],
            'employee_id'  => ['required', 'uuid'], // Logical reference, validate UUID format only
            'project_role' => ['required', 'string', 'max:100'],
            'joined_at'    => ['nullable', 'date'],
        ];
    }
}