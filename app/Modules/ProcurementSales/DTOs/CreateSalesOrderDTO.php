<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreateSalesOrderDTO
{
    /**
     * @param SalesOrderItemDTO[] $items
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $currencyId,
        public readonly string $orderDate,
        public readonly ?string $deliveryDate,
        public readonly ?string $warehouseId,
        public readonly array $items,
    ) {}
}
