<?php

namespace App\Modules\MasterData\DTOs;

readonly class CreateWarehouseDTO
{
    public function __construct(
        public string $tenantId,
        public string $code,
        public string $name,
        public ?string $location = null,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data, string $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            code: $data['code'],
            name: $data['name'],
            location: $data['location'] ?? null,
            isActive: $data['is_active'] ?? true,
        );
    }
}