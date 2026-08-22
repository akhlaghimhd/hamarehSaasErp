<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\HrManagement\DTOs\CreatePayrollRecordDTO;
use App\Modules\HrManagement\Models\PayrollRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PayrollRecordService
{
    public function generatePayroll(CreatePayrollRecordDTO $dto): PayrollRecord
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = app('current_tenant_id');

                $payroll = PayrollRecord::create([
                    'tenant_id' => $tenantId,
                    'employee_id' => $dto->employeeId,
                    'fiscal_period_id' => $dto->fiscalPeriodId,
                    'base_salary' => $dto->baseSalary,
                    'allowances_total' => $dto->allowancesTotal,
                    'deductions_total' => $dto->deductionsTotal,
                    'tax_withheld' => $dto->taxWithheld,
                    'insurance_premium' => $dto->insurancePremium,
                    // is_disbursed در ابتدا FALSE است و net_payable توسط دیتابیس محاسبه می‌شود
                ]);

                // رویداد ناهمگام برای ماژول حسابداری (ثبت سند حسابداری حقوق و دستمزد)
                // EventOutbox::create(['event_type' => 'hr.payroll.generated', ...]);

                return $payroll;
            });
        } catch (Exception $e) {
            Log::error('Failed to generate payroll record: ' . $e->getMessage(), [
                'employee_id' => $dto->employeeId,
                'fiscal_period_id' => $dto->fiscalPeriodId,
                'tenant_id' => app('current_tenant_id') ?? 'UNKNOWN'
            ]);
            throw $e;
        }
    }
}