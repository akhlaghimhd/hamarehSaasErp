<?php

namespace App\Modules\Accounting\DTOs;

use App\Modules\Accounting\Requests\UpdateTaxTransactionRequest;

readonly class UpdateTaxTransactionDTO
{
    public function __construct(
        public ?string $transactionDate = null,
        public ?int $taxType = null,
        public ?float $baseAmount = null,
        public ?float $taxAmount = null,
        public ?float $taxRate = null,
        public ?string $referenceDocumentType = null,
        public ?string $referenceDocumentId = null,
        public ?string $businessPartnerId = null
    ) {}

    public static function fromRequest(UpdateTaxTransactionRequest $request): self
    {
        $v = $request->validated();

        return new self(
            transactionDate: $v['transaction_date'] ?? null,
            taxType: isset($v['tax_type']) ? (int) $v['tax_type'] : null,
            baseAmount: isset($v['base_amount']) ? (float) $v['base_amount'] : null,
            taxAmount: isset($v['tax_amount']) ? (float) $v['tax_amount'] : null,
            taxRate: isset($v['tax_rate']) ? (float) $v['tax_rate'] : null,
            referenceDocumentType: $v['reference_document_type'] ?? null,
            referenceDocumentId: $v['reference_document_id'] ?? null,
            businessPartnerId: $v['business_partner_id'] ?? null
        );
    }
}
