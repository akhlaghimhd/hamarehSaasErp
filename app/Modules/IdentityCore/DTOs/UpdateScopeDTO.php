<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class UpdateScopeDTO
{
    public function __construct(
        public string $scopeId,
        public ?string $scopeName = null,
        public ?string $scopeType = null,
        public ?string $referenceId = null,
        public ?string $description = null,
        public ?bool $isActive = null
    ) {}

    public static function fromRequest(string $scopeId, array $validatedData): self
    {
        return new self(
            scopeId: $scopeId,
            scopeName: $validatedData['scope_name'] ?? null,
            scopeType: $validatedData['scope_type'] ?? null,
            referenceId: $validatedData['reference_id'] ?? null,
            description: $validatedData['description'] ?? null,
            isActive: $validatedData['is_active'] ?? null
        );
    }
}