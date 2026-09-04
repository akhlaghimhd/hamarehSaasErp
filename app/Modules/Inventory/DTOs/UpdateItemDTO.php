<?php

namespace App\Modules\Inventory\DTOs;

readonly class UpdateItemDTO
{
    public function __construct(
        public ?string $name = null,
        public ?int $item_type = null,
        public ?string $base_uom = null,
        public ?int $status = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            item_type: isset($data['item_type']) ? (int) $data['item_type'] : null,
            base_uom: $data['base_uom'] ?? null,
            status: isset($data['status']) ? (int) $data['status'] : null
        );
    }
}
