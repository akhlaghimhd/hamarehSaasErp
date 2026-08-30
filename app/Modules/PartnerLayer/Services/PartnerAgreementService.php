<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerAgreement;
use App\Modules\PartnerLayer\DTOs\CreatePartnerAgreementDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerAgreementDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;

/**
 * P3-S4 — Partner commercial agreements.
 *
 * agreement_number unique per partner (soft-deleted ignored by default).
 */
class PartnerAgreementService
{
    public function getAgreements(?string $partnerId = null): Collection
    {
        $query = PartnerAgreement::query()->orderBy('created_at', 'desc');

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

    public function getAgreementById(string $agreementId): PartnerAgreement
    {
        $agreement = PartnerAgreement::query()
            ->where('agreement_id', $agreementId)
            ->firstOrFail();

        $this->assertPartnerAccessible($agreement->partner_id);

        return $agreement;
    }

    public function createAgreement(CreatePartnerAgreementDTO $dto): PartnerAgreement
    {
        $this->assertPartnerAccessible($dto->partnerId);

        $exists = PartnerAgreement::query()
            ->where('partner_id', $dto->partnerId)
            ->where('agreement_number', $dto->agreementNumber)
            ->exists();

        if ($exists) {
            throw new Exception('Agreement number already exists for this partner.');
        }

        return PartnerAgreement::create([
            'partner_id'         => $dto->partnerId,
            'agreement_number'   => $dto->agreementNumber,
            'agreement_type'     => $dto->agreementType,
            'start_date'         => $dto->startDate ?? now(),
            'end_date'           => $dto->endDate,
            'payment_cycle'      => $dto->paymentCycle,
            'description'        => $dto->description,
            'status'             => $dto->status,
        ]);
    }

    public function updateAgreement(string $agreementId, UpdatePartnerAgreementDTO $dto): PartnerAgreement
    {
        $agreement = $this->getAgreementById($agreementId);

        if ($dto->agreementNumber !== null
            && $dto->agreementNumber !== $agreement->agreement_number
        ) {
            $exists = PartnerAgreement::query()
                ->where('partner_id', $agreement->partner_id)
                ->where('agreement_number', $dto->agreementNumber)
                ->where('agreement_id', '!=', $agreementId)
                ->exists();

            if ($exists) {
                throw new Exception('Agreement number already exists for this partner.');
            }
        }

        $agreement->update([
            'agreement_number' => $dto->agreementNumber ?? $agreement->agreement_number,
            'agreement_type'   => $dto->agreementType ?? $agreement->agreement_type,
            'start_date'       => array_key_exists('start_date', $dto->raw)
                ? $dto->startDate
                : $agreement->start_date,
            'end_date'         => array_key_exists('end_date', $dto->raw)
                ? $dto->endDate
                : $agreement->end_date,
            'payment_cycle'    => $dto->paymentCycle ?? $agreement->payment_cycle,
            'description'      => array_key_exists('description', $dto->raw)
                ? $dto->description
                : $agreement->description,
            'status'           => $dto->status ?? $agreement->status,
        ]);

        return $agreement->fresh();
    }

    public function deleteAgreement(string $agreementId): void
    {
        $agreement = $this->getAgreementById($agreementId);
        $agreement->delete();
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
