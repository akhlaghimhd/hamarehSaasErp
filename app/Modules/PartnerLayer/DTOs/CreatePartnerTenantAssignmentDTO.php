<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class CreatePartnerTenantAssignmentDTO
{
    public function __construct(
        public string $partnerId,
        public string $tenantId,
        public int $assignmentType = 1,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $transferReason = null,
        public ?string $assignedBy = null,
        public int $status = 1,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            partnerId: $validatedData['partner_id'],
            tenantId: $validatedData['tenant_id'],
            assignmentType: (int) ($validatedData['assignment_type'] ?? 1),
            startDate: $validatedData['start_date'] ?? null,
            endDate: $validatedData['end_date'] ?? null,
            transferReason: $validatedData['transfer_reason'] ?? null,
            assignedBy: $validatedData['assigned_by'] ?? null,
            status: (int) ($validatedData['status'] ?? 1),
        );
    }
}
