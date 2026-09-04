<?php

namespace App\Modules\Inventory\DTOs;

readonly class UpdateInventoryDocumentItemDTO
{
    public function __construct(
        public ?float $quantity = null,
        public ?float $unit_cost = null,
        public ?string $from_location_id = null,
        public ?string $to_location_id = null,
        public ?string $batch_number = null,
        public ?int $sort_order = null,
        public bool $clear_from_location = false,
        public bool $clear_to_location = false,
        public bool $clear_batch_number = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            quantity: isset($data['quantity']) ? (float) $data['quantity'] : null,
            unit_cost: isset($data['unit_cost']) ? (float) $data['unit_cost'] : null,
            from_location_id: array_key_exists('from_location_id', $data) ? $data['from_location_id'] : null,
            to_location_id: array_key_exists('to_location_id', $data) ? $data['to_location_id'] : null,
            batch_number: array_key_exists('batch_number', $data) ? $data['batch_number'] : null,
            sort_order: isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            clear_from_location: array_key_exists('from_location_id', $data) && $data['from_location_id'] === null,
            clear_to_location: array_key_exists('to_location_id', $data) && $data['to_location_id'] === null,
            clear_batch_number: array_key_exists('batch_number', $data) && $data['batch_number'] === null,
        );
    }
}
