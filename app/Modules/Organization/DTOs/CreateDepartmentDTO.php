<?php

namespace App\Modules\Organization\DTOs;

class CreateDepartmentDTO
{
    public function __construct(
        public readonly string $branchId,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $parentDepartmentId = null,
        public readonly ?string $managerUserId = null, // Logical Reference to Identity
        public readonly bool $isActive = true
    ) {}
}