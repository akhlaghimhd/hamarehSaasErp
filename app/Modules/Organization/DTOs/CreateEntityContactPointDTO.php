<?php

namespace App\Modules\Organization\DTOs;

readonly class CreateEntityContactPointDTO
{
    public function __construct(
        public string $entityType,
        public string $entityId,
        public int $contactPointType,
        public string $contactValue,
        public bool $isPrimary = false,
        public int $status = 1,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            entityType: $validatedData['entity_type'],
            entityId: $validatedData['entity_id'],
            contactPointType: (int) $validatedData['contact_point_type'],
            contactValue: $validatedData['contact_value'],
            isPrimary: (bool) ($validatedData['is_primary'] ?? false),
            status: (int) ($validatedData['status'] ?? 1),
        );
    }
}
