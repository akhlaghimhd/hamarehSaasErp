<?php

namespace App\Modules\MasterData\DTOs;

readonly class CreateCostCenterDTO
{
    public function __construct(
        public string $tenantId,
        public string $code,
        public string $name,
        public int $type,
        public int $status,
        public ?string $companyId = null,
        public ?string $departmentId = null,
        public ?string $parentCostCenterId = null,
    ) {}

    public static function fromArray(array $data, string $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            code: $data['code'],
            name: $data['name'],
            type: $data['type'],
            status: $data['status'] ?? 1,
            companyId: $data['company_id'] ?? null,
            departmentId: $data['department_id'] ?? null,
            parentCostCenterId: $data['parent_cost_center_id'] ?? null,
        );
    }
}