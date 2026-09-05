<?php

namespace App\Modules\ProcurementSales\DTOs;

class PurchaseReceiptItemDTO
{
    public function __construct(
        public readonly string $itemId,
        public readonly float $receivedQuantity,
        public readonly float $unitPrice,
        public readonly ?float $orderedQuantity = null,
        public readonly ?string $purchaseOrderItemId = null,
        public readonly ?string $uomCode = null,
        public readonly int $lineNumber = 1,
        public readonly ?string $notes = null,
    ) {}
}
