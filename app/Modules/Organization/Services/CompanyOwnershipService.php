<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\CompanyOwnership;
use App\Base\Context\TenantContext;
use Illuminate\Support\Str;

/**
 * ORG-P1-04 — ownership percent / relation between companies (same tenant).
 */
class CompanyOwnershipService
{
    public function create(
        string $companyId,
        string $ownerCompanyId,
        float $ownershipPercent,
        string $relationType = 'EQUITY',
        ?string $validFrom = null,
        ?string $validTo = null,
    ): CompanyOwnership {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $this->assertCompanyInTenant($tenantId, $companyId);
        $this->assertCompanyInTenant($tenantId, $ownerCompanyId);

        if ($companyId === $ownerCompanyId) {
            throw new \Exception('شرکت نمی‌تواند مالک خودش باشد.');
        }

        if ($ownershipPercent <= 0 || $ownershipPercent > 100) {
            throw new \Exception('درصد مالکیت باید بین ۰ و ۱۰۰ باشد.');
        }

        return CompanyOwnership::create([
            'ownership_id'       => (string) Str::uuid(),
            'tenant_id'          => $tenantId,
            'company_id'         => $companyId,
            'owner_company_id'   => $ownerCompanyId,
            'ownership_percent'  => $ownershipPercent,
            'relation_type'      => strtoupper($relationType),
            'valid_from'         => $validFrom,
            'valid_to'           => $validTo,
            'status'             => 1,
            'row_version'        => 1,
        ]);
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

    private function assertCompanyInTenant(string $tenantId, string $companyId): void
    {
        $exists = Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->exists();

        if (!$exists) {
            throw new \Exception('شرکت در این سازمان یافت نشد.');
        }
    }
}
