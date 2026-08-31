<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerAgreement;
use App\Modules\PartnerLayer\DTOs\CreatePartnerAgreementDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerAgreementDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * P3-S4 — Partner commercial agreements.
 * P3-X1 — Versioned outbox events on create/delete.
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

        return DB::transaction(function () use ($dto) {
            $agreement = PartnerAgreement::create([
                'partner_id'       => $dto->partnerId,
                'agreement_number' => $dto->agreementNumber,
                'agreement_type'   => $dto->agreementType,
                'start_date'       => $dto->startDate ?? now(),
                'end_date'         => $dto->endDate,
                'payment_cycle'    => $dto->paymentCycle,
                'description'      => $dto->description,
                'status'           => $dto->status,
            ]);

            $tenantId = TenantContext::getInstance()->getTenantId();
            if ($tenantId) {
                $this->logEventOutbox(
                    $tenantId,
                    'partner_agreements',
                    $agreement->agreement_id,
                    'PartnerLayer.PartnerAgreementCreated.v1',
                    [
                        'agreement_id'     => $agreement->agreement_id,
                        'partner_id'       => $agreement->partner_id,
                        'agreement_number' => $agreement->agreement_number,
                        'status'           => $agreement->status,
                    ]
                );
            }

            return $agreement;
        });
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

        DB::transaction(function () use ($agreement) {
            $tenantId = TenantContext::getInstance()->getTenantId();

            $agreement->delete();

            if ($tenantId) {
                $this->logEventOutbox(
                    $tenantId,
                    'partner_agreements',
                    $agreement->agreement_id,
                    'PartnerLayer.PartnerAgreementDeleted.v1',
                    [
                        'agreement_id' => $agreement->agreement_id,
                        'partner_id'   => $agreement->partner_id,
                    ]
                );
            }
        });
    }

    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
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
