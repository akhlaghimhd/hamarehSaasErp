<?php

namespace App\Modules\Inventory\DTOs;

readonly class UpdateStockBatchDTO
{
    public function __construct(
        public ?string $batch_number = null,
        public ?string $quantity_produced = null,
        public ?string $quantity_remaining = null,
        public ?string $production_date = null,
        public ?string $expiration_date = null,
        public ?int $qc_status = null,
        public bool $clear_production_date = false,
        public bool $clear_expiration_date = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            batch_number: $data['batch_number'] ?? null,
            quantity_produced: isset($data['quantity_produced']) ? (string) $data['quantity_produced'] : null,
            quantity_remaining: isset($data['quantity_remaining']) ? (string) $data['quantity_remaining'] : null,
            production_date: array_key_exists('production_date', $data) ? $data['production_date'] : null,
            expiration_date: array_key_exists('expiration_date', $data) ? $data['expiration_date'] : null,
            qc_status: isset($data['qc_status']) ? (int) $data['qc_status'] : null,
            clear_production_date: array_key_exists('production_date', $data) && $data['production_date'] === null,
            clear_expiration_date: array_key_exists('expiration_date', $data) && $data['expiration_date'] === null,
        );
    }
}
