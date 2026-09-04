<?php

namespace App\Modules\Inventory\DTOs;

readonly class UpdateWarehouseDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $branch_id = null,
        public ?bool $is_bonded = null,
        public ?int $status = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            branch_id: $data['branch_id'] ?? null,
            is_bonded: isset($data['is_bonded']) ? (bool) $data['is_bonded'] : null,
            status: isset($data['status']) ? (int) $data['status'] : null,
        );
    }
}
