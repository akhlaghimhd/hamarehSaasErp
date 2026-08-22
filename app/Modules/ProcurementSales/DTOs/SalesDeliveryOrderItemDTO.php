<?php

namespace App\Modules\ProcurementSales\DTOs;

class SalesDeliveryOrderItemDTO
{
    public function __construct(
        public readonly string $itemId,
        public readonly float $deliveredQuantity,
        public readonly float $unitPrice
    ) {}
}