<?php

namespace App\Modules\ProcurementSales\DTOs;

class SalesQuotationItemDTO
{
    public function __construct(
        public readonly string $itemId,
        public readonly float $quantity,
        public readonly float $unitPrice
    ) {}
}