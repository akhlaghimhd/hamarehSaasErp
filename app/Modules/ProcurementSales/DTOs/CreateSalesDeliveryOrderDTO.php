<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreateSalesDeliveryOrderDTO
{
    /**
     * @param SalesDeliveryOrderItemDTO[] $items
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $warehouseId,
        public readonly string $shippingDate,
        public readonly ?string $salesOrderId,
        public readonly array $items,
        public readonly ?string $shippingAddress = null,
    ) {}
}
