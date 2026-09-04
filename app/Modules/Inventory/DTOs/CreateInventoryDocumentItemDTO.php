<?php

namespace App\Modules\Inventory\DTOs;

readonly class CreateInventoryDocumentItemDTO
{
    public function __construct(
        public string $document_id,
        public string $item_id,
        public float $quantity,
        public float $unit_cost = 0.0,
        public ?string $from_location_id = null,
        public ?string $to_location_id = null,
        public ?string $batch_number = null,
        public int $sort_order = 0,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            document_id: $data['document_id'],
            item_id: $data['item_id'],
            quantity: (float) $data['quantity'],
            unit_cost: isset($data['unit_cost']) ? (float) $data['unit_cost'] : 0.0,
            from_location_id: $data['from_location_id'] ?? null,
            to_location_id: $data['to_location_id'] ?? null,
            batch_number: $data['batch_number'] ?? null,
            sort_order: isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
        );
    }
}
