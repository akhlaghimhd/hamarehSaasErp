<?php

namespace App\Modules\MasterData\DTOs;

readonly class UpdateCostCenterDTO
{
    public function __construct(
        public ?string $name = null,
        public ?int $type = null,
        public ?int $status = null,
        public ?string $company_id = null,
        public ?string $department_id = null,
        public ?string $parent_cost_center_id = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            type: isset($data['type']) ? (int) $data['type'] : null,
            status: isset($data['status']) ? (int) $data['status'] : null,
            company_id: $data['company_id'] ?? null,
            department_id: $data['department_id'] ?? null,
            parent_cost_center_id: $data['parent_cost_center_id'] ?? null
        );
    }
}