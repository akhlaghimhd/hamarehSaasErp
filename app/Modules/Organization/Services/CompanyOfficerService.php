<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\CompanyOfficer;
use App\Base\Context\TenantContext;
use Illuminate\Support\Str;

class CompanyOfficerService
{
    public function create(array $data): CompanyOfficer
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $companyId = $data['company_id'];

        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        return CompanyOfficer::create([
            'officer_id'            => (string) Str::uuid(),
            'tenant_id'             => $tenantId,
            'company_id'            => $companyId,
            'role_code'             => strtoupper((string) $data['role_code']),
            'role_title'            => $data['role_title'] ?? null,
            'full_name'             => $data['full_name'],
            'person_user_id'        => $data['person_user_id'] ?? null,
            'national_id'           => $data['national_id'] ?? null,
            'mandate_from'          => $data['mandate_from'] ?? null,
            'mandate_to'            => $data['mandate_to'] ?? null,
            'has_signing_authority' => (bool) ($data['has_signing_authority'] ?? false),
            'mandate_notes'         => $data['mandate_notes'] ?? null,
            'is_active'             => (bool) ($data['is_active'] ?? true),
            'row_version'           => 1,
        ]);
    }

    public function listForCompany(string $companyId)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        return CompanyOfficer::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->orderBy('role_code')
            ->orderBy('full_name')
            ->get();
    }

    public function softDelete(string $officerId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = CompanyOfficer::where('tenant_id', $tenantId)
            ->where('officer_id', $officerId)
            ->firstOrFail();
        $row->delete();
    }
}
