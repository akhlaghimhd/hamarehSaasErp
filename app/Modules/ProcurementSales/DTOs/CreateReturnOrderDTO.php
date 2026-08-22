<?php

namespace App\Modules\ProcurementSales\DTOs;

class CreateReturnOrderDTO
{
    public function __construct(
        public readonly string $businessPartnerId,
        public readonly string $sourceDocumentType,
        public readonly string $sourceDocumentId,
        public readonly string $returnDate,
        public readonly array $items
    ) {}
}