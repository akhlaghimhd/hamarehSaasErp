<?php

namespace App\Modules\Manufacturing\DTOs;

readonly class BomDTO
{
    /**
     * @param BomItemDTO[] $items
     */
    public function __construct(
        public string $item_id,
        public string $bom_code,
        public int $version_number,
        public bool $is_active,
        public float $batch_size,
        public ?string $description,
        public int $status,
        public string $created_by,
        public array $items
    ) {}
}