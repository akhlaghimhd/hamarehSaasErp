<?php

namespace App\Modules\Inventory\DTOs;

readonly class CreateInventoryDocumentDTO
{
    public function __construct(
        public string $fiscal_period_id,
        public int $document_type,
        public string $document_number,
        public ?string $posting_date = null,
        public ?string $source_document_type = null,
        public ?string $source_document_id = null,
        public ?string $business_partner_id = null,
        public ?string $description = null,
        public int $status = 1,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            fiscal_period_id: $data['fiscal_period_id'],
            document_type: (int) $data['document_type'],
            document_number: $data['document_number'],
            posting_date: $data['posting_date'] ?? null,
            source_document_type: $data['source_document_type'] ?? null,
            source_document_id: $data['source_document_id'] ?? null,
            business_partner_id: $data['business_partner_id'] ?? null,
            description: $data['description'] ?? null,
            status: isset($data['status']) ? (int) $data['status'] : 1,
        );
    }
}
