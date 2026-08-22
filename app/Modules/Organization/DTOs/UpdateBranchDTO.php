<?php

namespace App\Modules\Organization\DTOs;

class UpdateBranchDTO
{
    public function __construct(
        public readonly string $companyId,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $address = null,
        public readonly bool $isActive = true
    ) {}
}