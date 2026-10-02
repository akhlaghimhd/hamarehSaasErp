<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class CreateScopeDTO
{
    /**
     * @param  list<string>  $referenceIds  same-type entity ids (1..n for structural types)
     */
    public function __construct(
        public string $scopeName,
        public string $scopeType,
        public array $referenceIds = [],
        public ?string $description = null,
        public bool $isActive = true
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        $ids = [];
        if (!empty($validatedData['reference_ids']) && is_array($validatedData['reference_ids'])) {
            $ids = array_values(array_unique(array_filter(array_map(
                static fn ($v) => is_string($v) ? trim($v) : (string) $v,
                $validatedData['reference_ids']
            ))));
        } elseif (!empty($validatedData['reference_id'])) {
            $ids = [trim((string) $validatedData['reference_id'])];
        }

        return new self(
            scopeName: $validatedData['scope_name'],
            scopeType: $validatedData['scope_type'],
            referenceIds: $ids,
            description: $validatedData['description'] ?? null,
            isActive: $validatedData['is_active'] ?? true
        );
    }

    public function primaryReferenceId(): ?string
    {
        return $this->referenceIds[0] ?? null;
    }
}
