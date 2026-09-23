<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\PurchasingOrganization;
use App\Modules\Organization\Models\PurchOrgAssignment;
use App\Base\Context\TenantContext;
use Illuminate\Support\Str;

class PurchasingOrganizationService
{
    public function create(string $code, string $name, ?string $companyId = null): PurchasingOrganization
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (PurchasingOrganization::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new \Exception('کد سازمان خرید تکراری است.');
        }

        if ($companyId) {
            Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();
        }

        return PurchasingOrganization::create([
            'purch_org_id' => (string) Str::uuid(),
            'tenant_id'    => $tenantId,
            'code'         => $code,
            'name'         => $name,
            'company_id'   => $companyId,
            'is_active'    => true,
            'row_version'  => 1,
        ]);
    }

    public function assign(string $purchOrgId, ?string $companyId = null, ?string $branchId = null): PurchOrgAssignment
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        PurchasingOrganization::where('tenant_id', $tenantId)->where('purch_org_id', $purchOrgId)->firstOrFail();

        if ($companyId) {
            Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();
        }
        if ($branchId) {
            Branch::where('tenant_id', $tenantId)->where('branch_id', $branchId)->firstOrFail();
        }
        if (!$companyId && !$branchId) {
            throw new \Exception('حداقل یکی از company_id یا branch_id الزامی است.');
        }

        return PurchOrgAssignment::create([
            'assignment_id' => (string) Str::uuid(),
            'tenant_id'     => $tenantId,
            'purch_org_id'  => $purchOrgId,
            'company_id'    => $companyId,
            'branch_id'     => $branchId,
            'is_active'     => true,
            'row_version'   => 1,
        ]);
    }

    public function listForTenant()
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return PurchasingOrganization::where('tenant_id', $tenantId)->orderBy('code')->get();
    }
}
