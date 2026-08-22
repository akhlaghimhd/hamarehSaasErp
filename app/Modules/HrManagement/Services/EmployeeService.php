<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\HrManagement\Models\Employee;
use App\Modules\HrManagement\DTOs\CreateEmployeeDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class EmployeeService
{
    public function createEmployee(CreateEmployeeDTO $dto): Employee
    {
        try {
            return DB::transaction(function () use ($dto) {
                $employee = Employee::create([
                    'tenant_id'           => $dto->tenantId,
                    'business_partner_id' => $dto->businessPartnerId,
                    'user_id'             => $dto->userId,
                    'employee_code'       => $dto->employeeCode,
                    'employment_type'     => $dto->employmentType,
                    'hire_date'           => $dto->hireDate,
                    'termination_date'    => $dto->terminationDate,
                    'job_title'           => $dto->jobTitle,
                    'department_id'       => $dto->departmentId,
                    'branch_id'           => $dto->branchId,
                    'status'              => $dto->status,
                ]);

                Log::info("Employee created successfully.", ['id' => $employee->id, 'tenant_id' => $dto->tenantId]);

                return $employee;
            });
        } catch (Exception $e) {
            Log::error("Failed to create Employee: " . $e->getMessage(), ['tenant_id' => $dto->tenantId]);
            throw $e;
        }
    }
}