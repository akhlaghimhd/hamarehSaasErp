<?php

namespace App\Modules\Organization\DTOs;

class UpdateDepartmentDTO
{
    public function __construct(
        public readonly string $branchId,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $parentDepartmentId = null,
        public readonly ?string $managerUserId = null,
        public readonly bool $isActive = true
    ) {}
}