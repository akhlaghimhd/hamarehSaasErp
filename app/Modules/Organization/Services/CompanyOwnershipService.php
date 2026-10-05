<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\CompanyOwnership;
use App\Base\Context\TenantContext;
use Illuminate\Support\Str;

/**
 * ORG-P1-04 — ownership percent / relation.
 * Supports same-tenant company owners AND external natural/legal persons
 * (stored only on this row — never creates users/companies/branches).
 */
class CompanyOwnershipService
{
    public const KIND_COMPANY = 'COMPANY';

    public const KIND_EXTERNAL_PERSON = 'EXTERNAL_PERSON';

    public const KIND_EXTERNAL_ORG = 'EXTERNAL_ORG';

    public function create(
        string $companyId,
        float $ownershipPercent,
        string $relationType = 'EQUITY',
        string $ownerKind = self::KIND_COMPANY,
        ?string $ownerCompanyId = null,
        ?string $ownerDisplayName = null,
        ?string $ownerIdentifier = null,
        ?string $validFrom = null,
        ?string $validTo = null,
        ?string $excludeOwnershipId = null,
    ): CompanyOwnership {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $ownerKind = strtoupper($ownerKind);

        $this->assertCompanyInTenant($tenantId, $companyId);
        $this->assertPercent($ownershipPercent);
        $this->assertOwnerPayload($tenantId, $companyId, $ownerKind, $ownerCompanyId, $ownerDisplayName);
        $this->assertTotalNotExceed($tenantId, $companyId, $ownershipPercent, $excludeOwnershipId);

        return CompanyOwnership::create([
            'ownership_id'        => (string) Str::uuid(),
            'tenant_id'           => $tenantId,
            'company_id'          => $companyId,
            'owner_kind'          => $ownerKind,
            'owner_company_id'    => $ownerKind === self::KIND_COMPANY ? $ownerCompanyId : null,
            'owner_display_name'  => $ownerKind === self::KIND_COMPANY ? null : trim((string) $ownerDisplayName),
            'owner_identifier'    => $ownerKind === self::KIND_COMPANY
                ? null
                : ($ownerIdentifier !== null && $ownerIdentifier !== '' ? trim($ownerIdentifier) : null),
            'ownership_percent'   => $ownershipPercent,
            'relation_type'       => strtoupper($relationType),
            'valid_from'          => $validFrom,
            'valid_to'            => $validTo,
            'status'              => 1,
            'row_version'         => 1,
        ]);
    }

    public function update(
        string $ownershipId,
        float $ownershipPercent,
        string $relationType = 'EQUITY',
        string $ownerKind = self::KIND_COMPANY,
        ?string $ownerCompanyId = null,
        ?string $ownerDisplayName = null,
        ?string $ownerIdentifier = null,
        ?string $validFrom = null,
        ?string $validTo = null,
    ): CompanyOwnership {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $ownerKind = strtoupper($ownerKind);

        $row = CompanyOwnership::where('tenant_id', $tenantId)
            ->where('ownership_id', $ownershipId)
            ->firstOrFail();

        $this->assertPercent($ownershipPercent);
        $this->assertOwnerPayload($tenantId, $row->company_id, $ownerKind, $ownerCompanyId, $ownerDisplayName);
        $this->assertTotalNotExceed($tenantId, $row->company_id, $ownershipPercent, $ownershipId);

        $row->owner_kind = $ownerKind;
        $row->owner_company_id = $ownerKind === self::KIND_COMPANY ? $ownerCompanyId : null;
        $row->owner_display_name = $ownerKind === self::KIND_COMPANY ? null : trim((string) $ownerDisplayName);
        $row->owner_identifier = $ownerKind === self::KIND_COMPANY
            ? null
            : ($ownerIdentifier !== null && $ownerIdentifier !== '' ? trim($ownerIdentifier) : null);
        $row->ownership_percent = $ownershipPercent;
        $row->relation_type = strtoupper($relationType);
        $row->valid_from = $validFrom;
        $row->valid_to = $validTo;
        $row->row_version = (int) $row->row_version + 1;
        $row->save();

        return $row->fresh();
    }

    public function listForCompany(string $companyId)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $this->assertCompanyInTenant($tenantId, $companyId);

        return CompanyOwnership::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->orderByDesc('ownership_percent')
            ->get();
    }

    public function softDelete(string $ownershipId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = CompanyOwnership::where('tenant_id', $tenantId)
            ->where('ownership_id', $ownershipId)
            ->firstOrFail();

        $row->delete();
    }

    private function assertPercent(float $ownershipPercent): void
    {
        if ($ownershipPercent <= 0 || $ownershipPercent > 100) {
            throw new \Exception('درصد مالکیت باید بیشتر از ۰ و حداکثر ۱۰۰ باشد.');
        }
    }

    private function assertTotalNotExceed(
        string $tenantId,
        string $companyId,
        float $newPercent,
        ?string $excludeOwnershipId
    ): void {
        $q = CompanyOwnership::where('tenant_id', $tenantId)
            ->where('company_id', $companyId);

        if ($excludeOwnershipId) {
            $q->where('ownership_id', '!=', $excludeOwnershipId);
        }

        $existing = (float) $q->sum('ownership_percent');
        $total = round($existing + $newPercent, 4);

        if ($total > 100.0001) {
            $remain = max(0, round(100 - $existing, 4));
            throw new \Exception(
                'جمع درصد مالکیت نمی‌تواند از ۱۰۰ بیشتر شود. باقی‌مانده قابل ثبت: '.$remain.'٪'
            );
        }
    }

    private function assertOwnerPayload(
        string $tenantId,
        string $companyId,
        string $ownerKind,
        ?string $ownerCompanyId,
        ?string $ownerDisplayName,
    ): void {
        if ($ownerKind === self::KIND_COMPANY) {
            if (! $ownerCompanyId) {
                throw new \Exception('شرکت مالک الزامی است.');
            }
            $this->assertCompanyInTenant($tenantId, $ownerCompanyId);
            if ($companyId === $ownerCompanyId) {
                throw new \Exception('شرکت نمی‌تواند مالک خودش باشد.');
            }

            return;
        }

        if (! in_array($ownerKind, [self::KIND_EXTERNAL_PERSON, self::KIND_EXTERNAL_ORG], true)) {
            throw new \Exception('نوع سهامدار نامعتبر است.');
        }

        $name = trim((string) $ownerDisplayName);
        if ($name === '') {
            throw new \Exception('نام سهامدار خارجی الزامی است.');
        }
    }

    private function assertCompanyInTenant(string $tenantId, string $companyId): void
    {
        $exists = Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $exists) {
            throw new \Exception('شرکت در این سازمان یافت نشد.');
        }
    }
}
