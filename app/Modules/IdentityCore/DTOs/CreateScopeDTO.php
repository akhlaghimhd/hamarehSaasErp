<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class CreateScopeDTO
{
    public function __construct(
        public string $scopeName,
        public string $scopeType,
        public ?string $referenceId = null,
        public ?string $description = null,
        public bool $isActive = true
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            scopeName: $validatedData['scope_name'],
            scopeType: $validatedData['scope_type'],
            referenceId: $validatedData['reference_id'] ?? null,
            description: $validatedData['description'] ?? null,
            isActive: $validatedData['is_active'] ?? true
        );
    }
}