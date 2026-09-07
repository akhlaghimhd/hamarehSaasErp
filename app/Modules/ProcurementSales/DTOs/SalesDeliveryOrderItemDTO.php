<?php

namespace App\Modules\ProcurementSales\DTOs;

class SalesDeliveryOrderItemDTO
{
    public function __construct(
        public readonly string $itemId,
        public readonly float $deliveredQuantity,
        public readonly float $unitPrice = 0.0,
        public readonly ?float $orderedQuantity = null,
        public readonly ?string $salesOrderItemId = null,
        public readonly ?string $uomCode = null,
        public readonly int $lineNumber = 1,
        public readonly ?string $notes = null,
    ) {}
}
