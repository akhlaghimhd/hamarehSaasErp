<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\BusinessUnit;
use App\Modules\Organization\Models\BusinessUnitCompany;
use App\Modules\Organization\Models\Company;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class BusinessUnitService
{
    public function listForTenant(bool $onlyTrashed = false)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = BusinessUnit::where('tenant_id', $tenantId)
            ->with(['companyAssignments.company'])
            ->orderBy('code');

        if ($onlyTrashed) {
            $query->onlyTrashed();
        }

        return $query->get();
    }

    public function find(string $businessUnitId, bool $withTrashed = false): BusinessUnit
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = BusinessUnit::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->with(['companyAssignments.company']);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
    }

    public function create(string $code, string $name, ?string $description = null, bool $isActive = true): BusinessUnit
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (BusinessUnit::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new Exception('کد واحد کسب‌وکار تکراری است.');
        }

        return BusinessUnit::create([
            'business_unit_id' => (string) Str::uuid(),
            'tenant_id'        => $tenantId,
            'code'             => $code,
            'name'             => $name,
            'description'      => $description,
            'is_active'        => $isActive,
            'row_version'      => 1,
        ]);
    }

    public function update(
        string $businessUnitId,
        string $code,
        string $name,
        ?string $description = null,
        ?bool $isActive = null
    ): BusinessUnit {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $bu = BusinessUnit::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->firstOrFail();

        if ($bu->code !== $code) {
            if (BusinessUnit::where('tenant_id', $tenantId)
                ->where('code', $code)
                ->where('business_unit_id', '!=', $businessUnitId)
                ->exists()) {
                throw new Exception('کد واحد کسب‌وکار تکراری است.');
            }
        }

        $payload = [
            'code'        => $code,
            'name'        => $name,
            'description' => $description,
            'row_version' => ((int) ($bu->row_version ?? 1)) + 1,
        ];

        if ($isActive !== null) {
            $payload['is_active'] = $isActive;
        }

        $bu->update($payload);

        return $bu->fresh(['companyAssignments.company']);
    }

    public function softDelete(string $businessUnitId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $bu = BusinessUnit::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->firstOrFail();

        $bu->delete();
    }

    public function restore(string $businessUnitId): BusinessUnit
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $bu = BusinessUnit::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->firstOrFail();

        if (BusinessUnit::where('tenant_id', $tenantId)
            ->where('code', $bu->code)
            ->exists()) {
            throw new Exception('کد این واحد کسب‌وکار با یک رکورد فعال دیگر تداخل دارد.');
        }

        $bu->restore();

        $bu->update([
            'is_active'   => false,
            'row_version' => ((int) ($bu->row_version ?? 1)) + 1,
        ]);

        return $bu->fresh(['companyAssignments.company']);
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
            if ($isPrimary && !$existing->is_primary) {
                return DB::transaction(function () use ($tenantId, $businessUnitId, $existing) {
                    BusinessUnitCompany::where('tenant_id', $tenantId)
                        ->where('business_unit_id', $businessUnitId)
                        ->where('is_primary', true)
                        ->update(['is_primary' => false]);

                    $existing->update([
                        'is_primary'  => true,
                        'row_version' => ((int) ($existing->row_version ?? 1)) + 1,
                    ]);

                    return $existing->fresh();
                });
            }

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
}
