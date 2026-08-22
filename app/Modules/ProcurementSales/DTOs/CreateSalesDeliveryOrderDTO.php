<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreateSalesDeliveryOrderDTO
{
    /**
     * @param string|null $salesOrderId
     * @param string $customerId
     * @param string $warehouseId
     * @param string $deliveryDate
     * @param SalesDeliveryOrderItemDTO[] $items
     */
    public function __construct(
        public readonly ?string $salesOrderId,
        public readonly string $customerId,
        public readonly string $warehouseId,
        public readonly string $deliveryDate,
        public readonly array $items
    ) {}
}