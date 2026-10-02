<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class UpdateScopeDTO
{
    /**
     * @param  list<string>|null  $referenceIds  null = do not change members
     */
    public function __construct(
        public string $scopeId,
        public ?string $scopeName = null,
        public ?string $scopeType = null,
        public ?array $referenceIds = null,
        public ?string $description = null,
        public ?bool $isActive = null
    ) {}

    public static function fromRequest(string $scopeId, array $validatedData): self
    {
        $ids = null;
        if (array_key_exists('reference_ids', $validatedData) && is_array($validatedData['reference_ids'])) {
            $ids = array_values(array_unique(array_filter(array_map(
                static fn ($v) => is_string($v) ? trim($v) : (string) $v,
                $validatedData['reference_ids']
            ))));
        } elseif (array_key_exists('reference_id', $validatedData)) {
            $rid = $validatedData['reference_id'];
            $ids = ($rid === null || $rid === '') ? [] : [trim((string) $rid)];
        }

        return new self(
            scopeId: $scopeId,
            scopeName: $validatedData['scope_name'] ?? null,
            scopeType: $validatedData['scope_type'] ?? null,
            referenceIds: $ids,
            description: $validatedData['description'] ?? null,
            isActive: $validatedData['is_active'] ?? null
        );
    }
}
