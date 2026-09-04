<?php

namespace App\Modules\Inventory\DTOs;

readonly class UpdateItemDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?int $item_type = null,
        public ?int $valuation_method = null,
        public ?array $extra_attributes = null,
        public ?string $item_group_id = null,
        public ?string $uom_id = null,
        public ?int $status = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            item_type: isset($data['item_type']) ? (int) $data['item_type'] : null,
            valuation_method: isset($data['valuation_method']) ? (int) $data['valuation_method'] : null,
            extra_attributes: array_key_exists('extra_attributes', $data) ? $data['extra_attributes'] : null,
            item_group_id: $data['item_group_id'] ?? null,
            uom_id: $data['uom_id'] ?? null,
            status: isset($data['status']) ? (int) $data['status'] : null,
        );
    }
}
