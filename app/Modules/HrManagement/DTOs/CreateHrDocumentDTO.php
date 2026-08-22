<?php

namespace App\Modules\HrManagement\DTOs;

readonly class CreateHrDocumentDTO
{
    public function __construct(
        public string $employee_id,
        public string $document_type_code,
        public string $document_title,
        public ?string $issue_date,
        public ?string $expiry_date,
        public ?string $attachment_id,
        public int $status
    ) {}
}