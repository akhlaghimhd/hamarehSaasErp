<?php

namespace App\Modules\Organization\DTOs;

readonly class CreateDepartmentDTO
{
    public function __construct(
        public string $branchId,
        public string $code,
        public string $name,
        public ?string $parentDepartmentId = null,
        public ?string $managerUserId = null,
        public bool $isActive = true,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            branchId: $validatedData['branch_id'],
            code: $validatedData['code'],
            name: $validatedData['name'],
            parentDepartmentId: $validatedData['parent_department_id'] ?? null,
            managerUserId: $validatedData['manager_user_id'] ?? null,
            isActive: (bool) ($validatedData['is_active'] ?? true),
        );
    }
}