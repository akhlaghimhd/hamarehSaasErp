<?php

namespace App\Modules\HrManagement\DTOs;

readonly class CreatePayrollRecordDTO
{
    public function __construct(
        public string $employeeId,
        public string $fiscalPeriodId,
        public float $baseSalary,
        public float $allowancesTotal,
        public float $deductionsTotal,
        public float $taxWithheld,
        public float $insurancePremium,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            employeeId: $validated['employee_id'],
            fiscalPeriodId: $validated['fiscal_period_id'],
            baseSalary: (float) $validated['base_salary'],
            allowancesTotal: (float) ($validated['allowances_total'] ?? 0),
            deductionsTotal: (float) ($validated['deductions_total'] ?? 0),
            taxWithheld: (float) ($validated['tax_withheld'] ?? 0),
            insurancePremium: (float) ($validated['insurance_premium'] ?? 0),
        );
    }
}