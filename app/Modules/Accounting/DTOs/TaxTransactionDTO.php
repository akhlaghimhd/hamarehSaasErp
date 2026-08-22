<?php

namespace App\Modules\Accounting\DTOs;

class TaxTransactionDTO
{
    public function __construct(
        public readonly string $transactionDate,
        public readonly int $taxType, // 1: Value Added Tax (VAT), 2: Withholding Tax, 3: Income Tax
        public readonly float $baseAmount,
        public readonly float $taxAmount,
        public readonly float $taxRate,
        public readonly ?string $referenceDocumentType = null, // e.g., 'SALES_INVOICE', 'PURCHASE_RECEIPT'
        public readonly ?string $referenceDocumentId = null, // Logical Ref
        public readonly ?string $businessPartnerId = null // Logical Ref to MasterData
    ) {}
}