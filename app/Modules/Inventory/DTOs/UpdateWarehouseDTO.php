<?php

namespace App\Modules\Inventory\DTOs;

readonly class UpdateWarehouseDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $location = null,
        public ?bool $isActive = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            location: $data['location'] ?? null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null
        );
    }
}
