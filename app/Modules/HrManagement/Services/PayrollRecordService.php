<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\HrManagement\Models\Employee;
use App\Modules\HrManagement\Models\PayrollRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-HR-02 — Payroll record create + mark disbursed.
 * net_payable is DB-generated; do not write it.
 */
class PayrollRecordService
{
    public function listForEmployee(string $employeeId): Collection
    {
        return PayrollRecord::query()
            ->where('employee_id', $employeeId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getById(string $id): PayrollRecord
    {
        $row = PayrollRecord::query()->find($id);
        if (!$row) {
            throw new NotFoundHttpException('Payroll record not found.');
        }

        return $row;
    }

    /**
     * @param  array{employee_id:string,fiscal_period_id:string,base_salary:float,allowances_total?:float,deductions_total?:float,tax_withheld?:float,insurance_premium?:float}  $data
     */
    public function create(array $data): PayrollRecord
    {
        try {
            return DB::transaction(function () use ($data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $employee = Employee::query()->find($data['employee_id']);
                if (!$employee) {
                    throw new NotFoundHttpException('Employee not found.');
                }
                if ((int) $employee->status === EmployeeService::STATUS_TERMINATED) {
                    throw new ConflictHttpException('Cannot create payroll for terminated employee.');
                }

                $dup = PayrollRecord::query()
                    ->where('employee_id', $data['employee_id'])
                    ->where('fiscal_period_id', $data['fiscal_period_id'])
                    ->first();
                if ($dup) {
                    throw new ConflictHttpException('Payroll already exists for this employee and fiscal period.');
                }

                return PayrollRecord::create([
                    'tenant_id'          => $tenantId,
                    'employee_id'        => $data['employee_id'],
                    'fiscal_period_id'   => $data['fiscal_period_id'],
                    'base_salary'        => $data['base_salary'],
                    'allowances_total'   => $data['allowances_total'] ?? 0,
                    'deductions_total'   => $data['deductions_total'] ?? 0,
                    'tax_withheld'       => $data['tax_withheld'] ?? 0,
                    'insurance_premium'  => $data['insurance_premium'] ?? 0,
                    'is_disbursed'       => false,
                    'created_by'         => $userId,
                    'row_version'        => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to create payroll: ' . $e->getMessage());
            throw $e;
        }
    }

    public function markDisbursed(string $id, ?string $journalEntryId = null): PayrollRecord
    {
        $row = PayrollRecord::query()->lockForUpdate()->find($id);
        if (!$row) {
            throw new NotFoundHttpException('Payroll record not found.');
        }
        if ($row->is_disbursed) {
            throw new ConflictHttpException('Payroll is already disbursed.');
        }

        $row->update([
            'is_disbursed'      => true,
            'disbursed_at'      => now(),
            'journal_entry_id'  => $journalEntryId,
            'updated_by'        => Context::get('user_id'),
            'row_version'       => ((int) ($row->row_version ?? 1)) + 1,
        ]);

        return $row->fresh();
    }
}
