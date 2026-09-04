<?php

namespace App\Modules\Inventory\DTOs;

readonly class CreateStockBatchDTO
{
    public function __construct(
        public string $item_id,
        public string $batch_number,
        public string $quantity_produced,
        public string $quantity_remaining,
        public ?string $production_date = null,
        public ?string $expiration_date = null,
        public int $qc_status = 1,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            item_id: $data['item_id'],
            batch_number: $data['batch_number'],
            quantity_produced: (string) $data['quantity_produced'],
            quantity_remaining: (string) ($data['quantity_remaining'] ?? $data['quantity_produced']),
            production_date: $data['production_date'] ?? null,
            expiration_date: $data['expiration_date'] ?? null,
            qc_status: isset($data['qc_status']) ? (int) $data['qc_status'] : 1,
        );
    }
}
