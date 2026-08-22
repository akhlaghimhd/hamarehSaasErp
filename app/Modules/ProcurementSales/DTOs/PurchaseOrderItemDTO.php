<?php

namespace App\Modules\ProcurementSales\DTOs;

class PurchaseOrderItemDTO
{
    public function __construct(
        public readonly string $itemId,
        public readonly float $quantity,
        public readonly float $unitPrice
    ) {}
}