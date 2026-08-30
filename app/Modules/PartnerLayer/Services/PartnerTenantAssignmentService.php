<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerTenantAssignment;
use App\Modules\PartnerLayer\DTOs\CreatePartnerTenantAssignmentDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerTenantAssignmentDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;

/**
 * P3-S3 — Assign tenants (logical tenant_id) to partners.
 *
 * No physical FK to tenants table (Law 2.2 / 2.3).
 */
class PartnerTenantAssignmentService
{
    public function getAssignments(?string $partnerId = null): Collection
    {
        $query = PartnerTenantAssignment::query()->orderBy('created_at', 'desc');

        if ($partnerId) {
            $this->assertPartnerAccessible($partnerId);
            $query->where('partner_id', $partnerId);
        } else {
            $ids = $this->accessiblePartnerIds();
            if ($ids->isEmpty()) {
                return collect();
            }
            $query->whereIn('partner_id', $ids);
        }

        return $query->get();
    }

    public function getAssignmentById(string $assignmentId): PartnerTenantAssignment
    {
        $assignment = PartnerTenantAssignment::query()
            ->where('assignment_id', $assignmentId)
            ->firstOrFail();

        $this->assertPartnerAccessible($assignment->partner_id);

        return $assignment;
    }

    public function createAssignment(CreatePartnerTenantAssignmentDTO $dto): PartnerTenantAssignment
    {
        $this->assertPartnerAccessible($dto->partnerId);

        $activeExists = PartnerTenantAssignment::query()
            ->where('partner_id', $dto->partnerId)
            ->where('tenant_id', $dto->tenantId)
            ->where('status', 1)
            ->whereNull('end_date')
            ->exists();

        if ($activeExists) {
            throw new Exception('An active assignment already exists for this partner and tenant.');
        }

        return PartnerTenantAssignment::create([
            'partner_id'        => $dto->partnerId,
            'tenant_id'         => $dto->tenantId,
            'assignment_type'   => $dto->assignmentType,
            'start_date'        => $dto->startDate ?? now(),
            'end_date'          => $dto->endDate,
            'transfer_reason'   => $dto->transferReason,
            'assigned_by'       => $dto->assignedBy,
            'status'            => $dto->status,
        ]);
    }

    public function updateAssignment(string $assignmentId, UpdatePartnerTenantAssignmentDTO $dto): PartnerTenantAssignment
    {
        $assignment = $this->getAssignmentById($assignmentId);

        $assignment->update([
            'assignment_type' => $dto->assignmentType ?? $assignment->assignment_type,
            'end_date'        => array_key_exists('end_date', $dto->raw)
                ? $dto->endDate
                : $assignment->end_date,
            'transfer_reason' => array_key_exists('transfer_reason', $dto->raw)
                ? $dto->transferReason
                : $assignment->transfer_reason,
            'status'          => $dto->status ?? $assignment->status,
        ]);

        return $assignment->fresh();
    }

    public function deleteAssignment(string $assignmentId): void
    {
        $assignment = $this->getAssignmentById($assignmentId);
        $assignment->delete();
    }

    private function assertPartnerAccessible(string $partnerId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = Partner::query()->where('partner_id', $partnerId);

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
        }

        if (!$query->exists()) {
            throw new Exception('Partner not found or not accessible in this context.');
        }
    }

    private function accessiblePartnerIds(): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = Partner::query()->select('partner_id');

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
        }

        return $query->pluck('partner_id');
    }
}
