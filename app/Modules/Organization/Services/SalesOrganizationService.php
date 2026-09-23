<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\SalesOrganization;
use App\Modules\Organization\Models\SalesOrgAssignment;
use App\Base\Context\TenantContext;
use Illuminate\Support\Str;

class SalesOrganizationService
{
    public function create(string $code, string $name, ?string $companyId = null): SalesOrganization
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (SalesOrganization::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new \Exception('کد سازمان فروش تکراری است.');
        }

        if ($companyId) {
            Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();
        }

        return SalesOrganization::create([
            'sales_org_id' => (string) Str::uuid(),
            'tenant_id'    => $tenantId,
            'code'         => $code,
            'name'         => $name,
            'company_id'   => $companyId,
            'is_active'    => true,
            'row_version'  => 1,
        ]);
    }

    public function assign(string $salesOrgId, ?string $companyId = null, ?string $branchId = null): SalesOrgAssignment
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        SalesOrganization::where('tenant_id', $tenantId)->where('sales_org_id', $salesOrgId)->firstOrFail();

        if ($companyId) {
            Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();
        }
        if ($branchId) {
            Branch::where('tenant_id', $tenantId)->where('branch_id', $branchId)->firstOrFail();
        }
        if (!$companyId && !$branchId) {
            throw new \Exception('حداقل یکی از company_id یا branch_id الزامی است.');
        }

        return SalesOrgAssignment::create([
            'assignment_id' => (string) Str::uuid(),
            'tenant_id'     => $tenantId,
            'sales_org_id'  => $salesOrgId,
            'company_id'    => $companyId,
            'branch_id'     => $branchId,
            'is_active'     => true,
            'row_version'   => 1,
        ]);
    }

    public function listForTenant()
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return SalesOrganization::where('tenant_id', $tenantId)->orderBy('code')->get();
    }
}
