<?php

namespace App\Modules\ProcurementSales\DTOs;

readonly class PurchaseRequisitionItemDTO
{
    public function __construct(
        public string $itemId,
        public float $quantity,
        public float $estimatedUnitPrice = 0.0,
        public ?string $uomCode = null,
        public int $lineNumber = 1,
        public ?string $description = null,
    ) {}
}
