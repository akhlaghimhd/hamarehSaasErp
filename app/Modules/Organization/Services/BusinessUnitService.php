<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\BusinessUnit;
use App\Modules\Organization\Models\BusinessUnitCompany;
use App\Modules\Organization\Models\Company;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessUnitService
{
    public function create(string $code, string $name, ?string $description = null): BusinessUnit
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (BusinessUnit::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new \Exception('کد واحد کسب‌وکار تکراری است.');
        }

        return BusinessUnit::create([
            'business_unit_id' => (string) Str::uuid(),
            'tenant_id'        => $tenantId,
            'code'             => $code,
            'name'             => $name,
            'description'      => $description,
            'is_active'        => true,
            'row_version'      => 1,
        ]);
    }

    public function assignCompany(string $businessUnitId, string $companyId, bool $isPrimary = false): BusinessUnitCompany
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        BusinessUnit::where('tenant_id', $tenantId)->where('business_unit_id', $businessUnitId)->firstOrFail();
        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        $existing = BusinessUnitCompany::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->where('company_id', $companyId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($tenantId, $businessUnitId, $companyId, $isPrimary) {
            if ($isPrimary) {
                BusinessUnitCompany::where('tenant_id', $tenantId)
                    ->where('business_unit_id', $businessUnitId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            return BusinessUnitCompany::create([
                'assignment_id'    => (string) Str::uuid(),
                'tenant_id'        => $tenantId,
                'business_unit_id' => $businessUnitId,
                'company_id'       => $companyId,
                'is_primary'       => $isPrimary,
                'is_active'        => true,
                'row_version'      => 1,
            ]);
        });
    }

    public function listForTenant()
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return BusinessUnit::where('tenant_id', $tenantId)
            ->orderBy('code')
            ->get();
    }
}
