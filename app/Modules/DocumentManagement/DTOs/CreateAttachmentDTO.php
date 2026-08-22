<?php
namespace App\Modules\DocumentManagement\DTOs;

class CreateAttachmentDTO {
    public function __construct(
        public readonly string $targetEntityType,
        public readonly string $targetEntityId,
        public readonly string $fileName,
        public readonly string $filePath,
        public readonly string $mimeType,
        public readonly int $fileSizeBytes,
        public readonly ?string $fileHash = null
    ) {}
}