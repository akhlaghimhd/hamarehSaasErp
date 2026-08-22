<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreateSalesQuotationDTO
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $quotationDate,
        public readonly ?string $validUntil,
        public readonly array $items
    ) {}
}