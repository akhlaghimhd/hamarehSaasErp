<?php

namespace App\Modules\Organization\DTOs;

readonly class CreateBranchDTO
{
    public function __construct(
        public string $companyId,
        public string $code,
        public string $name,
        public ?string $address = null,
        public bool $isActive = true,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            companyId: $validatedData['company_id'],
            code: $validatedData['code'],
            name: $validatedData['name'],
            address: $validatedData['address'] ?? null,
            isActive: (bool) ($validatedData['is_active'] ?? true),
        );
    }
}