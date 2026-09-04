<?php

namespace App\Modules\Inventory\DTOs;

readonly class CreateLocationDTO
{
    public function __construct(
        public string $warehouse_id,
        public string $code,
        public string $name,
        public ?string $parent_location_id = null,
        public ?string $aisle = null,
        public ?string $rack = null,
        public ?string $shelf = null,
        public int $status = 1,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            warehouse_id: $data['warehouse_id'],
            code: $data['code'],
            name: $data['name'],
            parent_location_id: $data['parent_location_id'] ?? null,
            aisle: $data['aisle'] ?? null,
            rack: $data['rack'] ?? null,
            shelf: $data['shelf'] ?? null,
            status: isset($data['status']) ? (int) $data['status'] : 1,
        );
    }
}
