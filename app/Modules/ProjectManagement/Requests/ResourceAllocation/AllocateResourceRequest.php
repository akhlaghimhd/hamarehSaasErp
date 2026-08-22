<?php

namespace App\Modules\ProjectManagement\Requests\ResourceAllocation;

use Illuminate\Foundation\Http\FormRequest;

class AllocateResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id'            => ['required', 'uuid', 'exists:project_tasks,task_id'], // Allowed since it's same module
            'resource_type'      => ['required', 'integer', 'in:1,2,3'],
            'resource_id'        => ['required', 'uuid'], // Logical cross-module reference (No exists rule)
            'allocated_quantity' => ['nullable', 'numeric', 'min:0'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}