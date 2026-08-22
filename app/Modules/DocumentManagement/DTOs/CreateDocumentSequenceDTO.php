<?php
namespace App\Modules\DocumentManagement\DTOs;

class CreateDocumentSequenceDTO {
    public function __construct(
        public readonly string $moduleCode,
        public readonly string $documentType,
        public readonly int $documentScope,
        public readonly ?string $companyId = null,
        public readonly ?string $ownerType = null,
        public readonly ?string $ownerId = null,
        public readonly ?string $prefix = null,
        public readonly ?string $suffix = null,
        public readonly int $paddingLength = 6,
        public readonly int $resetPeriod = 1
    ) {}
}