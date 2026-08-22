<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreateSalesOrderDTO
{
    /**
     * @param string $customerId
     * @param string $orderDate
     * @param string|null $expectedDeliveryDate
     * @param SalesOrderItemDTO[] $items
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $orderDate,
        public readonly ?string $expectedDeliveryDate,
        public readonly array $items
    ) {}
}