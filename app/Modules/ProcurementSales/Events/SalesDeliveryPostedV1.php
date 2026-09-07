<?php

namespace App\Modules\ProcurementSales\Events;

/**
 * L6-PS-05 – Boundary event: delivery posted → Inventory Issue + release reservation.
 */
final class SalesDeliveryPostedV1
{
    public const EVENT_TYPE = 'procurement.sales-delivery.posted.v1';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $deliveryOrderId,
        public readonly string $deliveryNumber,
        public readonly string $customerId,
        public readonly ?string $salesOrderId,
        public readonly string $shippingDate,
        /** @var array<int, array{item_id: string, quantity: string, unit_price: string, line_number: int}> */
        public readonly array $lines,
    ) {
    }

    public function toPayload(): array
    {
        return [
            'event_type'         => self::EVENT_TYPE,
            'tenant_id'          => $this->tenantId,
            'delivery_order_id'  => $this->deliveryOrderId,
            'delivery_number'    => $this->deliveryNumber,
            'customer_id'        => $this->customerId,
            'sales_order_id'     => $this->salesOrderId,
            'shipping_date'      => $this->shippingDate,
            'lines'              => $this->lines,
        ];
    }
}
