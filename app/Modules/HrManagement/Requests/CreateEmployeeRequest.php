<?php

namespace App\Modules\HrManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = request()->header('X-Tenant-ID');

        return [
            'employee_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_code')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'employment_type'     => ['required', 'integer', 'in:1,2,3'],
            'hire_date'           => ['required', 'date'],
            'business_partner_id' => ['nullable', 'uuid'],
            'user_id'             => ['nullable', 'uuid'],
            'job_title'           => ['nullable', 'string', 'max:150'],
            'department_id'       => ['nullable', 'uuid'],
            'branch_id'           => ['nullable', 'uuid'],
            'termination_date'    => ['nullable', 'date', 'after_or_equal:hire_date'],
            'status'              => ['sometimes', 'integer', 'in:1,2,3'],
        ];
    }
}