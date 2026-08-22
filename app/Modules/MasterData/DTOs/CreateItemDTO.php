<?php

namespace App\Modules\MasterData\DTOs;

readonly class CreateItemDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public int $item_type,
        public string $base_uom,
        public int $status = 1
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            code: $data['code'],
            name: $data['name'],
            item_type: (int) $data['item_type'],
            base_uom: $data['base_uom'],
            status: isset($data['status']) ? (int) $data['status'] : 1
        );
    }
}