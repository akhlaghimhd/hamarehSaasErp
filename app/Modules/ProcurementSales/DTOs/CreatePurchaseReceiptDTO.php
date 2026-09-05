<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreatePurchaseReceiptDTO
{
    /**
     * @param PurchaseReceiptItemDTO[] $items
     */
    public function __construct(
        public readonly string $purchaseOrderId,
        public readonly string $supplierId,
        public readonly string $warehouseId,
        public readonly string $receiptDate,
        public readonly array $items,
        public readonly ?string $notes = null,
    ) {}
}
