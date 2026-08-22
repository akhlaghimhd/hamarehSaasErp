<?php

namespace App\Modules\HrManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // احراز هویت مستأجر در TenantContextMiddleware انجام شده است
    }

    public function rules(): array
    {
        $tenantId = app('current_tenant_id');

        return [
            'employee_id' => [
                'required',
                'uuid',
                // اطمینان از اینکه کارمند متعلق به همین مستأجر است
                Rule::exists('employees', 'employee_id')->where('tenant_id', $tenantId),
            ],
            'date' => [
                'required',
                'date',
                // جلوگیری از ثبت رکورد تکراری حضور برای یک کارمند در یک روز مشخص
                Rule::unique('attendance_records')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId)
                                 ->where('employee_id', $this->input('employee_id'))
                                 ->where('date', $this->input('date'));
                })
            ],
            'check_in' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'check_out' => ['nullable', 'date_format:Y-m-d H:i:s', 'after_or_equal:check_in'],
            'status' => ['required', 'integer', 'in:1,2,3,4'],
            'work_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}