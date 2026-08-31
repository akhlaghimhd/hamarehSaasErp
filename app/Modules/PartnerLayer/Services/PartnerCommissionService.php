<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerCommission;
use App\Modules\PartnerLayer\Models\PartnerCommissionRule;
use App\Modules\PartnerLayer\DTOs\CreatePartnerCommissionDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerCommissionDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;

/**
 * P3-S6 — Calculated partner commissions.
 *
 * tenant_id / invoice_id / currency_id: logical references only (Law 2.2 / 2.3).
 * commission_rule_id: physical FK within PartnerLayer bounded context.
 */
class PartnerCommissionService
{
    public function getCommissions(?string $partnerId = null): Collection
    {
        $query = PartnerCommission::query()->orderBy('calculated_at', 'desc');

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

    public function getCommissionById(string $commissionId): PartnerCommission
    {
        $commission = PartnerCommission::query()
            ->where('commission_id', $commissionId)
            ->firstOrFail();

        $this->assertPartnerAccessible($commission->partner_id);

        return $commission;
    }

    public function createCommission(CreatePartnerCommissionDTO $dto): PartnerCommission
    {
        $this->assertPartnerAccessible($dto->partnerId);

        if ($dto->commissionRuleId) {
            $this->assertRuleAccessible($dto->commissionRuleId, $dto->partnerId);
        }

        if ((float) $dto->baseAmount < 0 || (float) $dto->commissionAmount < 0) {
            throw new Exception('base_amount and commission_amount must be non-negative.');
        }

        return PartnerCommission::create([
            'partner_id'                 => $dto->partnerId,
            'tenant_id'                  => $dto->tenantId,
            'invoice_id'                 => $dto->invoiceId,
            'commission_rule_id'         => $dto->commissionRuleId,
            'base_amount'                => $dto->baseAmount,
            'commission_type_snapshot'   => $dto->commissionTypeSnapshot,
            'commission_value_snapshot'  => $dto->commissionValueSnapshot,
            'commission_amount'          => $dto->commissionAmount,
            'currency_id'                => $dto->currencyId,
            'exchange_rate'              => $dto->exchangeRate,
            'status'                     => $dto->status,
            'calculated_at'              => $dto->calculatedAt ?? now(),
            'paid_at'                    => $dto->paidAt,
        ]);
    }

    public function updateCommission(string $commissionId, UpdatePartnerCommissionDTO $dto): PartnerCommission
    {
        $commission = $this->getCommissionById($commissionId);

        $commission->update([
            'status'  => $dto->status ?? $commission->status,
            'paid_at' => array_key_exists('paid_at', $dto->raw)
                ? $dto->paidAt
                : $commission->paid_at,
        ]);

        return $commission->fresh();
    }

    public function deleteCommission(string $commissionId): void
    {
        $commission = $this->getCommissionById($commissionId);
        $commission->delete();
    }

    private function assertRuleAccessible(string $ruleId, string $partnerId): void
    {
        $rule = PartnerCommissionRule::query()
            ->where('commission_rule_id', $ruleId)
            ->first();

        if (!$rule) {
            throw new Exception('Commission rule not found.');
        }

        $agreement = $rule->agreement;
        if (!$agreement || $agreement->partner_id !== $partnerId) {
            throw new Exception('Commission rule does not belong to the specified partner.');
        }
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
