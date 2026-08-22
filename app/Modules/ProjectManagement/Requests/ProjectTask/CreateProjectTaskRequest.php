<?php

namespace App\Modules\ProjectManagement\Requests\ProjectTask;

use Illuminate\Foundation\Http\FormRequest;

class CreateProjectTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id'     => ['required', 'uuid', 'exists:projects,project_id'],
            'parent_task_id' => ['nullable', 'uuid', 'exists:project_tasks,task_id'],
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'start_date'     => ['required', 'date'],
            'due_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'status'         => ['nullable', 'integer', 'in:1,2,3,4'],
            'priority'       => ['nullable', 'integer', 'in:1,2,3,4'],
        ];
    }
}