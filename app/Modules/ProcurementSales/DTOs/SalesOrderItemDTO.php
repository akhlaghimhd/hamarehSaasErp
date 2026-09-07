<?php

namespace App\Modules\ProcurementSales\DTOs;

class SalesOrderItemDTO
{
    public function __construct(
        public readonly string $itemId,
        public readonly float $quantity,
        public readonly float $unitPrice,
        public readonly float $discountAmount = 0.0,
        public readonly float $taxAmount = 0.0,
        public readonly ?string $uomCode = null,
        public readonly int $lineNumber = 1,
        public readonly ?string $description = null,
    ) {}
}
