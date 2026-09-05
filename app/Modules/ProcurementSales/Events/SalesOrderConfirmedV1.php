<?php

namespace App\Modules\ProcurementSales\Events;

/**
 * L6-PS-00 – Boundary event for stock reservation.
 * When Sales Order is Confirmed, Inventory may reserve stock.
 */
final class SalesOrderConfirmedV1
{
    public const EVENT_TYPE = 'procurement.sales-order.confirmed.v1';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $salesOrderId,
        public readonly string $orderNumber,
        public readonly string $customerId,
        /** @var array<int, array{item_id: string, quantity: string, line_number: int}> */
        public readonly array $lines,
    ) {
    }

    public function toPayload(): array
    {
        return [
            'event_type'     => self::EVENT_TYPE,
            'tenant_id'      => $this->tenantId,
            'sales_order_id' => $this->salesOrderId,
            'order_number'   => $this->orderNumber,
            'customer_id'    => $this->customerId,
            'lines'          => $this->lines,
        ];
    }
}
