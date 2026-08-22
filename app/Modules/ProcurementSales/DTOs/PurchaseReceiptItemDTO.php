<?php

namespace App\Modules\ProcurementSales\DTOs;

class PurchaseReceiptItemDTO
{
    public function __construct(
        public readonly string $itemId,
        public readonly float $receivedQuantity,
        public readonly float $unitPrice
    ) {}
}