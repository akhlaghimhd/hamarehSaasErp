<?php

namespace App\Modules\Inventory\DTOs;

readonly class UpdateLocationDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $parent_location_id = null,
        public ?string $aisle = null,
        public ?string $rack = null,
        public ?string $shelf = null,
        public ?int $status = null,
        public bool $clear_parent = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            parent_location_id: array_key_exists('parent_location_id', $data) ? $data['parent_location_id'] : null,
            aisle: array_key_exists('aisle', $data) ? $data['aisle'] : null,
            rack: array_key_exists('rack', $data) ? $data['rack'] : null,
            shelf: array_key_exists('shelf', $data) ? $data['shelf'] : null,
            status: isset($data['status']) ? (int) $data['status'] : null,
            clear_parent: array_key_exists('parent_location_id', $data) && $data['parent_location_id'] === null,
        );
    }
}
