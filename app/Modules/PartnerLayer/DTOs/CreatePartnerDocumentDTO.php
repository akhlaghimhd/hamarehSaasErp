<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class CreatePartnerDocumentDTO
{
    public function __construct(
        public string $partnerId,
        public string $documentType,
        public string $storagePath,
        public ?string $documentNumber = null,
        public int $status = 1,
        public ?string $verifiedAt = null,
        public ?string $verifiedBy = null,
    ) {}

    public static function fromRequest(array $d): self
    {
        return new self(
            partnerId: $d['partner_id'],
            documentType: $d['document_type'],
            storagePath: $d['storage_path'],
            documentNumber: $d['document_number'] ?? null,
            status: (int) ($d['status'] ?? 1),
            verifiedAt: $d['verified_at'] ?? null,
            verifiedBy: $d['verified_by'] ?? null,
        );
    }
}
