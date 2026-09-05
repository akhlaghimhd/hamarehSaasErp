<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreatePurchaseOrderDTO
{
    /**
     * @param PurchaseOrderItemDTO[] $items
     */
    public function __construct(
        public readonly string $supplierId,
        public readonly string $currencyId,
        public readonly string $orderDate,
        public readonly ?string $deliveryDate,
        public readonly array $items,
    ) {}
}
