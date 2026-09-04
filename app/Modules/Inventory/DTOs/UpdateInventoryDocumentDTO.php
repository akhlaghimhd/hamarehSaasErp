<?php

namespace App\Modules\Inventory\DTOs;

readonly class UpdateInventoryDocumentDTO
{
    public function __construct(
        public ?string $posting_date = null,
        public ?string $source_document_type = null,
        public ?string $source_document_id = null,
        public ?string $business_partner_id = null,
        public ?string $description = null,
        public ?int $status = null,
        public bool $clear_business_partner = false,
        public bool $clear_source = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            posting_date: $data['posting_date'] ?? null,
            source_document_type: array_key_exists('source_document_type', $data) ? $data['source_document_type'] : null,
            source_document_id: array_key_exists('source_document_id', $data) ? $data['source_document_id'] : null,
            business_partner_id: array_key_exists('business_partner_id', $data) ? $data['business_partner_id'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            status: isset($data['status']) ? (int) $data['status'] : null,
            clear_business_partner: array_key_exists('business_partner_id', $data) && $data['business_partner_id'] === null,
            clear_source: array_key_exists('source_document_id', $data) && $data['source_document_id'] === null,
        );
    }
}
