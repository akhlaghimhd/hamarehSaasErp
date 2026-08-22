<?php
namespace App\Modules\DocumentManagement\DTOs;

class CreateDocumentDTO {
    public function __construct(
        public readonly string $documentNumber,
        public readonly string $title,
        public readonly string $documentType,
        public readonly ?string $description = null,
        public readonly int $status = 1
    ) {}
}