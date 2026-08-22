<?php

namespace App\Modules\HrManagement\DTOs;

readonly class CreateEmployeeDTO
{
    public function __construct(
        public string $tenantId,
        public string $employeeCode,
        public int $employmentType,
        public string $hireDate,
        public ?string $businessPartnerId = null,
        public ?string $userId = null,
        public ?string $jobTitle = null,
        public ?string $departmentId = null,
        public ?string $branchId = null,
        public ?string $terminationDate = null,
        public int $status = 1,
    ) {}

    public static function fromArray(array $data, string $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            employeeCode: $data['employee_code'],
            employmentType: (int) $data['employment_type'],
            hireDate: $data['hire_date'],
            businessPartnerId: $data['business_partner_id'] ?? null,
            userId: $data['user_id'] ?? null,
            jobTitle: $data['job_title'] ?? null,
            departmentId: $data['department_id'] ?? null,
            branchId: $data['branch_id'] ?? null,
            terminationDate: $data['termination_date'] ?? null,
            status: isset($data['status']) ? (int) $data['status'] : 1,
        );
    }
}