<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class UpdatePartnerTenantAssignmentDTO
{
    public function __construct(
        public ?int $assignmentType = null,
        public ?string $endDate = null,
        public ?string $transferReason = null,
        public ?int $status = null,
        public array $raw = [],
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            assignmentType: array_key_exists('assignment_type', $validatedData)
                ? (int) $validatedData['assignment_type']
                : null,
            endDate: $validatedData['end_date'] ?? null,
            transferReason: $validatedData['transfer_reason'] ?? null,
            status: array_key_exists('status', $validatedData)
                ? (int) $validatedData['status']
                : null,
            raw: $validatedData,
        );
    }
}
