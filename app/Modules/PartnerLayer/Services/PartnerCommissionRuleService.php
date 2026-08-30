<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerAgreement;
use App\Modules\PartnerLayer\Models\PartnerCommissionRule;
use App\Modules\PartnerLayer\DTOs\CreatePartnerCommissionRuleDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerCommissionRuleDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;

/**
 * P3-S5 — Commission rules bound to partner_agreements (within PartnerLayer BC).
 */
class PartnerCommissionRuleService
{
    public function getRules(?string $agreementId = null): Collection
    {
        $query = PartnerCommissionRule::query()->orderBy('created_at', 'desc');

        if ($agreementId) {
            $this->assertAgreementAccessible($agreementId);
            $query->where('agreement_id', $agreementId);
        } else {
            $ids = $this->accessibleAgreementIds();
            if ($ids->isEmpty()) {
                return collect();
            }
            $query->whereIn('agreement_id', $ids);
        }

        return $query->get();
    }

    public function getRuleById(string $commissionRuleId): PartnerCommissionRule
    {
        $rule = PartnerCommissionRule::query()
            ->where('commission_rule_id', $commissionRuleId)
            ->firstOrFail();

        $this->assertAgreementAccessible($rule->agreement_id);

        return $rule;
    }

    public function createRule(CreatePartnerCommissionRuleDTO $dto): PartnerCommissionRule
    {
        $this->assertAgreementAccessible($dto->agreementId);

        if ($dto->minimumAmount !== null
            && $dto->maximumAmount !== null
            && (float) $dto->minimumAmount > (float) $dto->maximumAmount
        ) {
            throw new Exception('minimum_amount cannot be greater than maximum_amount.');
        }

        return PartnerCommissionRule::create([
            'agreement_id'      => $dto->agreementId,
            'revenue_type'      => $dto->revenueType,
            'commission_type'   => $dto->commissionType,
            'commission_value'  => $dto->commissionValue,
            'calculation_basis' => $dto->calculationBasis,
            'minimum_amount'    => $dto->minimumAmount,
            'maximum_amount'    => $dto->maximumAmount,
            'effective_from'    => $dto->effectiveFrom ?? now(),
            'effective_to'      => $dto->effectiveTo,
            'status'            => $dto->status,
        ]);
    }

    public function updateRule(string $commissionRuleId, UpdatePartnerCommissionRuleDTO $dto): PartnerCommissionRule
    {
        $rule = $this->getRuleById($commissionRuleId);

        $min = array_key_exists('minimum_amount', $dto->raw)
            ? $dto->minimumAmount
            : $rule->minimum_amount;
        $max = array_key_exists('maximum_amount', $dto->raw)
            ? $dto->maximumAmount
            : $rule->maximum_amount;

        if ($min !== null && $max !== null && (float) $min > (float) $max) {
            throw new Exception('minimum_amount cannot be greater than maximum_amount.');
        }

        $rule->update([
            'revenue_type'      => $dto->revenueType ?? $rule->revenue_type,
            'commission_type'   => $dto->commissionType ?? $rule->commission_type,
            'commission_value'  => $dto->commissionValue ?? $rule->commission_value,
            'calculation_basis' => $dto->calculationBasis ?? $rule->calculation_basis,
            'minimum_amount'    => array_key_exists('minimum_amount', $dto->raw)
                ? $dto->minimumAmount
                : $rule->minimum_amount,
            'maximum_amount'    => array_key_exists('maximum_amount', $dto->raw)
                ? $dto->maximumAmount
                : $rule->maximum_amount,
            'effective_from'    => array_key_exists('effective_from', $dto->raw)
                ? $dto->effectiveFrom
                : $rule->effective_from,
            'effective_to'      => array_key_exists('effective_to', $dto->raw)
                ? $dto->effectiveTo
                : $rule->effective_to,
            'status'            => $dto->status ?? $rule->status,
        ]);

        return $rule->fresh();
    }

    public function deleteRule(string $commissionRuleId): void
    {
        $rule = $this->getRuleById($commissionRuleId);
        $rule->delete();
    }

    private function assertAgreementAccessible(string $agreementId): void
    {
        $agreement = PartnerAgreement::query()
            ->where('agreement_id', $agreementId)
            ->first();

        if (!$agreement) {
            throw new Exception('Agreement not found or not accessible in this context.');
        }

        $this->assertPartnerAccessible($agreement->partner_id);
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

    private function accessibleAgreementIds(): Collection
    {
        $partnerIds = $this->accessiblePartnerIds();
        if ($partnerIds->isEmpty()) {
            return collect();
        }

        return PartnerAgreement::query()
            ->whereIn('partner_id', $partnerIds)
            ->pluck('agreement_id');
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
