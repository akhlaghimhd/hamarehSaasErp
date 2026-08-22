<?php

namespace App\Modules\ProjectManagement\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class CreateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorizations handled by Middleware/Policies
    }

    public function rules(): array
    {
        return [
            'project_code' => ['required', 'string', 'max:50'],
            'name'         => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string'],
            'start_date'   => ['required', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'       => ['nullable', 'integer', 'in:0,1,2,3,4'],
        ];
    }
}