<?php

namespace App\Modules\SaasAdmin\DTOs;

readonly class CreateTenantDTO
{
    public function __construct(
        public string $name,
        public ?string $domain
    ) {
    }

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            name: $validatedData['name'],
            domain: $validatedData['domain'] ?? null
        );
    }
}