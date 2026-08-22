<?php

namespace App\Modules\Organization\DTOs;

class CreateCompanyDTO
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $registrationNumber = null,
        public readonly ?string $economicCode = null,
        public readonly bool $isActive = true
    ) {}
}