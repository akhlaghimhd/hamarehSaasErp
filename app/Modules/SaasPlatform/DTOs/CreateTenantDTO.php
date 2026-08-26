<?php

namespace App\Modules\SaasPlatform\DTOs;

readonly class CreateTenantDTO
{
    public function __construct(
        public string $tenantCode,
        public string $tenantName,
        public ?string $legalName,
        public string $slug,
        public int $tenantType = 1,
    ) {
    }

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            tenantCode: $validatedData['tenant_code'],
            tenantName: $validatedData['tenant_name'],
            legalName: $validatedData['legal_name'] ?? null,
            slug: $validatedData['slug'],
            tenantType: (int) ($validatedData['tenant_type'] ?? 1),
        );
    }
}