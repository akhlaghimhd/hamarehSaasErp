<?php

namespace App\Modules\Inventory\DTOs;

readonly class CreateWarehouseDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public string $branch_id,
        public bool $is_bonded = false,
        public int $status = 1,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            name: $data['name'],
            branch_id: $data['branch_id'],
            is_bonded: isset($data['is_bonded']) ? (bool) $data['is_bonded'] : false,
            status: isset($data['status']) ? (int) $data['status'] : 1,
        );
    }
}
