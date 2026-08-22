<?php

namespace App\Modules\HrManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // در این لایه فرض بر این است که TenantContextMiddleware احراز هویت را انجام داده است
        return true;
    }

    public function rules(): array
    {
        $tenantId = app('current_tenant_id'); // دریافت شناسه مستأجر از کانتینر اختصاصی

        return [
            'employee_id' => [
                'required',
                'uuid',
                // چک کردن وجود کارمند در همین مستأجر
                Rule::exists('employees', 'employee_id')->where('tenant_id', $tenantId),
                // هر کارمند فقط یک پروفایل می‌تواند داشته باشد
                Rule::unique('employee_profiles', 'employee_id')->where('tenant_id', $tenantId)
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'national_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('employee_profiles', 'national_code')->where('tenant_id', $tenantId)
            ],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'integer', 'in:1,2,3'],
            'marital_status' => ['nullable', 'integer', 'in:1,2'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact' => ['nullable', 'string', 'max:50'],
        ];
    }
}