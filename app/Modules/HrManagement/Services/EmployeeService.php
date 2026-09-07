<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\HrManagement\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeService
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_SUSPENDED = 2;
    public const STATUS_TERMINATED = 3;

    public const TYPE_FULL_TIME = 1;
    public const TYPE_PART_TIME = 2;
    public const TYPE_CONTRACT = 3;

    public function list(): Collection
    {
        return Employee::query()->orderBy('employee_code')->get();
    }

    public function getById(string $id): Employee
    {
        $emp = Employee::query()->find($id);
        if (!$emp) {
            throw new NotFoundHttpException('Employee not found.');
        }

        return $emp;
    }

    public function create(array $data): Employee
    {
        try {
            return DB::transaction(function () use ($data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                return Employee::create([
                    'tenant_id'           => $tenantId,
                    'business_partner_id' => $data['business_partner_id'] ?? null,
                    'user_id'             => $data['user_id'] ?? null,
                    'employee_code'       => $data['employee_code'],
                    'employment_type'     => $data['employment_type'] ?? self::TYPE_FULL_TIME,
                    'hire_date'           => $data['hire_date'],
                    'termination_date'    => $data['termination_date'] ?? null,
                    'job_title'           => $data['job_title'] ?? null,
                    'department_id'       => $data['department_id'] ?? null,
                    'branch_id'           => $data['branch_id'] ?? null,
                    'status'              => $data['status'] ?? self::STATUS_ACTIVE,
                    'created_by'          => $userId,
                    'row_version'         => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to create Employee: ' . $e->getMessage());
            throw $e;
        }
    }

    public function update(string $id, array $data): Employee
    {
        $emp = Employee::query()->lockForUpdate()->find($id);
        if (!$emp) {
            throw new NotFoundHttpException('Employee not found.');
        }

        $payload = array_filter([
            'business_partner_id' => $data['business_partner_id'] ?? null,
            'user_id'             => $data['user_id'] ?? null,
            'employee_code'       => $data['employee_code'] ?? null,
            'employment_type'     => $data['employment_type'] ?? null,
            'hire_date'           => $data['hire_date'] ?? null,
            'termination_date'    => $data['termination_date'] ?? null,
            'job_title'           => $data['job_title'] ?? null,
            'department_id'       => $data['department_id'] ?? null,
            'branch_id'           => $data['branch_id'] ?? null,
            'status'              => $data['status'] ?? null,
            'updated_by'          => Context::get('user_id'),
        ], fn ($v) => $v !== null);

        $payload['row_version'] = ((int) ($emp->row_version ?? 1)) + 1;
        $emp->update($payload);

        return $emp->fresh();
    }

    public function terminate(string $id, string $terminationDate): Employee
    {
        $emp = Employee::query()->lockForUpdate()->find($id);
        if (!$emp) {
            throw new NotFoundHttpException('Employee not found.');
        }
        if ((int) $emp->status === self::STATUS_TERMINATED) {
            throw new ConflictHttpException('Employee is already terminated.');
        }

        $emp->update([
            'status'           => self::STATUS_TERMINATED,
            'termination_date' => $terminationDate,
            'updated_by'       => Context::get('user_id'),
            'row_version'      => ((int) ($emp->row_version ?? 1)) + 1,
        ]);

        return $emp->fresh();
    }
}
