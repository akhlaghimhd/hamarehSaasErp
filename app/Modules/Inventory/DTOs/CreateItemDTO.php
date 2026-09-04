<?php

namespace App\Modules\Inventory\DTOs;

readonly class CreateItemDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public string $item_group_id,
        public string $uom_id,
        public int $item_type = 1,
        public int $valuation_method = 1,
        public ?string $description = null,
        public ?array $extra_attributes = null,
        public int $status = 1,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            code: $data['code'],
            name: $data['name'],
            item_group_id: $data['item_group_id'],
            uom_id: $data['uom_id'],
            item_type: isset($data['item_type']) ? (int) $data['item_type'] : 1,
            valuation_method: isset($data['valuation_method']) ? (int) $data['valuation_method'] : 1,
            description: $data['description'] ?? null,
            extra_attributes: $data['extra_attributes'] ?? null,
            status: isset($data['status']) ? (int) $data['status'] : 1,
        );
    }
}
