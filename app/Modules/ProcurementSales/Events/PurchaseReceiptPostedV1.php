<?php

namespace App\Modules\ProcurementSales\Events;

/**
 * L6-PS-00 – Boundary event contract with Inventory.
 * When a Purchase Receipt is Posted, Inventory must create a Goods Receipt document.
 */
final class PurchaseReceiptPostedV1
{
    public const EVENT_TYPE = 'procurement.purchase-receipt.posted.v1';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $purchaseReceiptId,
        public readonly string $receiptNumber,
        public readonly string $supplierId,
        public readonly string $purchaseOrderId,
        public readonly string $receiptDate,
        /** @var array<int, array{item_id: string, quantity: string, unit_price: string, line_number: int}> */
        public readonly array $lines,
    ) {
    }

    public function toPayload(): array
    {
        return [
            'event_type'          => self::EVENT_TYPE,
            'tenant_id'           => $this->tenantId,
            'purchase_receipt_id' => $this->purchaseReceiptId,
            'receipt_number'      => $this->receiptNumber,
            'supplier_id'         => $this->supplierId,
            'purchase_order_id'   => $this->purchaseOrderId,
            'receipt_date'        => $this->receiptDate,
            'lines'               => $this->lines,
        ];
    }
}
