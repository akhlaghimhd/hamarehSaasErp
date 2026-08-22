<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreatePurchaseOrderDTO
{
    /**
     * @param string $supplierId
     * @param string $orderDate
     * @param string|null $expectedDeliveryDate
     * @param PurchaseOrderItemDTO[] $items
     */
    public function __construct(
        public readonly string $supplierId,
        public readonly string $orderDate,
        public readonly ?string $expectedDeliveryDate,
        public readonly array $items
    ) {}
}