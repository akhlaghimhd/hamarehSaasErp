<?php

namespace App\Modules\Manufacturing\DTOs;

readonly class BomItemDTO
{
    public function __construct(
        public string $component_item_id,
        public string $uom_id,
        public float $quantity,
        public float $scrap_percentage,
        public int $sort_order
    ) {}
}