<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreatePurchaseInvoiceDTO
{
    /**
     * @param PurchaseInvoiceItemDTO[] $items
     */
    public function __construct(
        public readonly string $supplierId,
        public readonly string $currencyId,
        public readonly string $invoiceDate,
        public readonly ?string $dueDate,
        public readonly ?string $purchaseOrderId,
        public readonly ?string $supplierInvoiceRef,
        public readonly ?string $taxInvoiceNumber,
        public readonly ?string $description,
        public readonly array $items,
    ) {}
}
