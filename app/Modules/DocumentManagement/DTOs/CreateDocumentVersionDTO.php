<?php
namespace App\Modules\DocumentManagement\DTOs;

class CreateDocumentVersionDTO {
    public function __construct(
        public readonly string $documentId,
        public readonly int $versionNumber,
        public readonly ?string $attachmentId = null,
        public readonly ?string $changeSummary = null
    ) {}
}