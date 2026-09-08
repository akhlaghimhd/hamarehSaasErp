<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreateSalesInvoiceDTO
{
    /**
     * @param SalesInvoiceItemDTO[] $items
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $currencyId,
        public readonly string $invoiceDate,
        public readonly ?string $dueDate,
        public readonly ?string $salesOrderId,
        public readonly ?string $taxInvoiceNumber,
        public readonly ?string $description,
        public readonly array $items,
    ) {}
}
