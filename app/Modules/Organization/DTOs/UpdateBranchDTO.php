<?php

namespace App\Modules\Organization\DTOs;

readonly class UpdateBranchDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $companyId = null,
        public ?string $address = null,
        public bool $isActive = true,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            code: $validatedData['code'],
            name: $validatedData['name'],
            companyId: $validatedData['company_id'] ?? null,
            address: $validatedData['address'] ?? null,
            isActive: (bool) ($validatedData['is_active'] ?? true),
        );
    }
}