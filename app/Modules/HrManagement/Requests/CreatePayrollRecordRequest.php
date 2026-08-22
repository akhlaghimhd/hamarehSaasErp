<?php

namespace App\Modules\HrManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePayrollRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app('current_tenant_id');

        return [
            'employee_id' => [
                'required',
                'uuid',
                Rule::exists('employees', 'employee_id')->where('tenant_id', $tenantId),
            ],
            'fiscal_period_id' => [
                'required',
                'uuid',
                // جلوگیری از ثبت دو فیش حقوقی برای یک کارمند در یک دوره مالی (طبق ایندکس uq_hr_payroll_period)
                Rule::unique('payroll_records')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId)
                                 ->where('employee_id', $this->input('employee_id'))
                                 ->where('fiscal_period_id', $this->input('fiscal_period_id'));
                })
            ],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowances_total' => ['nullable', 'numeric', 'min:0'],
            'deductions_total' => ['nullable', 'numeric', 'min:0'],
            'tax_withheld' => ['nullable', 'numeric', 'min:0'],
            'insurance_premium' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}