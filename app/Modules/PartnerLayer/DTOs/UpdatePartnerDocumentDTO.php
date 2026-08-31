<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class UpdatePartnerDocumentDTO
{
    public function __construct(
        public ?string $documentType = null,
        public ?string $documentNumber = null,
        public ?string $storagePath = null,
        public ?int $status = null,
        public ?string $verifiedAt = null,
        public ?string $verifiedBy = null,
        public array $raw = [],
    ) {}

    public static function fromRequest(array $d): self
    {
        return new self(
            documentType: $d['document_type'] ?? null,
            documentNumber: $d['document_number'] ?? null,
            storagePath: $d['storage_path'] ?? null,
            status: array_key_exists('status', $d) ? (int) $d['status'] : null,
            verifiedAt: $d['verified_at'] ?? null,
            verifiedBy: $d['verified_by'] ?? null,
            raw: $d,
        );
    }
}
