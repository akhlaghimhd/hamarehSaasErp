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

        $bu = BusinessUnit::create([
            'business_unit_id' => (string) Str::uuid(),
            'tenant_id'        => $tenantId,
            'code'             => $code,
            'name'             => $name,
            'description'      => $description,
            'is_active'        => $isActive,
            'row_version'      => 1,
        ]);

        HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->syncBusinessUnit($bu));

        return $bu;
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

        $fresh = $bu->fresh(['companyAssignments.company']) ?? $bu;
        HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->syncBusinessUnit($fresh));

        return $fresh;
    }

    public function softDelete(string $businessUnitId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $bu = BusinessUnit::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->firstOrFail();

        $id = (string) $bu->business_unit_id;
        $bu->delete();

        HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->deactivateEntityNodes('BUSINESS_UNIT', $id));
    }

    public function restore(string $businessUnitId): BusinessUnit
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $bu = BusinessUnit::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->firstOrFail();

        if (BusinessUnit::where('tenant_id', $tenantId)->where('code', $bu->code)->exists()) {
            throw new Exception('کد این واحد با یک واحد فعال دیگر تداخل دارد.');
        }

        $bu->restore();
        $bu->update([
            'is_active'   => false,
            'row_version' => ((int) ($bu->row_version ?? 1)) + 1,
        ]);

        $fresh = $bu->fresh(['companyAssignments.company']) ?? $bu;
        HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->syncBusinessUnit($fresh));

        return $fresh;
    }

    /**
     * @return array{row: BusinessUnitCompany, created: bool}
     */
    public function assignCompany(string $businessUnitId, string $companyId, bool $isPrimary = false): array
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $bu = BusinessUnit::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->firstOrFail();

        if (!$bu->is_active) {
            throw new Exception('واحد غیرفعال را نمی‌توان به شرکت متصل کرد. ابتدا واحد را فعال کنید.');
        }

        if (!Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->exists()) {
            throw new Exception('شرکت انتخاب شده نامعتبر است.');
        }

        $existing = BusinessUnitCompany::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->where('company_id', $companyId)
            ->first();

        $result = DB::transaction(function () use ($tenantId, $businessUnitId, $companyId, $isPrimary, $existing) {
            if ($isPrimary) {
                BusinessUnitCompany::where('tenant_id', $tenantId)
                    ->where('business_unit_id', $businessUnitId)
                    ->update(['is_primary' => false]);
            }

            if ($existing && !$existing->trashed()) {
                $existing->update([
                    'is_primary'  => $isPrimary || (bool) $existing->is_primary,
                    'is_active'   => true,
                    'row_version' => ((int) ($existing->row_version ?? 1)) + 1,
                ]);

                return ['row' => $existing->fresh(), 'created' => false];
            }

            if ($existing && $existing->trashed()) {
                $existing->restore();
                $existing->update([
                    'is_primary'  => $isPrimary,
                    'is_active'   => true,
                    'row_version' => ((int) ($existing->row_version ?? 1)) + 1,
                ]);

                return ['row' => $existing->fresh(), 'created' => true];
            }

            $row = BusinessUnitCompany::create([
                'assignment_id'    => (string) Str::uuid(),
                'tenant_id'        => $tenantId,
                'business_unit_id' => $businessUnitId,
                'company_id'       => $companyId,
                'is_primary'       => $isPrimary,
                'is_active'        => true,
                'row_version'      => 1,
            ]);

            return ['row' => $row, 'created' => true];
        });

        $bu = BusinessUnit::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->first();
        if ($bu) {
            HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->syncBusinessUnit($bu));
        }

        return $result;
    }

    public function unassignCompany(string $businessUnitId, string $companyId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        BusinessUnit::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->firstOrFail();

        $row = BusinessUnitCompany::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->where('company_id', $companyId)
            ->first();

        if (!$row) {
            throw new Exception('این واحد به این شرکت متصل نیست.');
        }

        $wasPrimary = (bool) $row->is_primary;
        $row->delete();

        if ($wasPrimary) {
            $next = BusinessUnitCompany::where('tenant_id', $tenantId)
                ->where('business_unit_id', $businessUnitId)
                ->orderBy('created_at')
                ->first();
            if ($next) {
                $next->update([
                    'is_primary'  => true,
                    'row_version' => ((int) ($next->row_version ?? 1)) + 1,
                ]);
            }
        }

        $bu = BusinessUnit::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->first();
        if ($bu) {
            HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->syncBusinessUnit($bu));
        }
    }

    /**
     * @param  list<string>  $companyIds
     * @return array{attached: int, detached: int}
     */
    public function syncCompanies(string $businessUnitId, array $companyIds, ?string $primaryCompanyId = null): array
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $bu = BusinessUnit::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->firstOrFail();

        $companyIds = array_values(array_unique(array_filter($companyIds)));

        if ($companyIds !== [] && !$bu->is_active) {
            throw new Exception('واحد غیرفعال را نمی‌توان به شرکت متصل کرد. ابتدا واحد را فعال کنید.');
        }

        foreach ($companyIds as $cid) {
            if (!Company::where('tenant_id', $tenantId)->where('company_id', $cid)->exists()) {
                throw new Exception('یکی از شرکت‌های انتخاب‌شده نامعتبر است.');
            }
        }

        if ($primaryCompanyId && !in_array($primaryCompanyId, $companyIds, true)) {
            $primaryCompanyId = $companyIds[0] ?? null;
        }

        $result = DB::transaction(function () use ($tenantId, $businessUnitId, $companyIds, $primaryCompanyId) {
            $current = BusinessUnitCompany::where('tenant_id', $tenantId)
                ->where('business_unit_id', $businessUnitId)
                ->get();

            $currentIds = $current->pluck('company_id')->all();
            $toDetach = array_diff($currentIds, $companyIds);
            $toAttach = array_diff($companyIds, $currentIds);

            $detached = 0;
            foreach ($toDetach as $cid) {
                BusinessUnitCompany::where('tenant_id', $tenantId)
                    ->where('business_unit_id', $businessUnitId)
                    ->where('company_id', $cid)
                    ->delete();
                $detached++;
            }

            $attached = 0;
            foreach ($toAttach as $cid) {
                $trashed = BusinessUnitCompany::onlyTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('business_unit_id', $businessUnitId)
                    ->where('company_id', $cid)
                    ->first();

                if ($trashed) {
                    $trashed->restore();
                    $trashed->update([
                        'is_primary'  => false,
                        'is_active'   => true,
                        'row_version' => ((int) ($trashed->row_version ?? 1)) + 1,
                    ]);
                } else {
                    BusinessUnitCompany::create([
                        'assignment_id'    => (string) Str::uuid(),
                        'tenant_id'        => $tenantId,
                        'business_unit_id' => $businessUnitId,
                        'company_id'       => $cid,
                        'is_primary'       => false,
                        'is_active'        => true,
                        'row_version'      => 1,
                    ]);
                }
                $attached++;
            }

            if ($primaryCompanyId) {
                BusinessUnitCompany::where('tenant_id', $tenantId)
                    ->where('business_unit_id', $businessUnitId)
                    ->update(['is_primary' => false]);

                BusinessUnitCompany::where('tenant_id', $tenantId)
                    ->where('business_unit_id', $businessUnitId)
                    ->where('company_id', $primaryCompanyId)
                    ->update(['is_primary' => true]);
            } else {
                $still = BusinessUnitCompany::where('tenant_id', $tenantId)
                    ->where('business_unit_id', $businessUnitId)
                    ->orderBy('created_at')
                    ->get();
                if ($still->isNotEmpty() && !$still->contains(fn ($r) => (bool) $r->is_primary)) {
                    $first = $still->first();
                    $first->update([
                        'is_primary'  => true,
                        'row_version' => ((int) ($first->row_version ?? 1)) + 1,
                    ]);
                }
            }

            return ['attached' => $attached, 'detached' => $detached];
        });

        $bu = BusinessUnit::where('tenant_id', $tenantId)
            ->where('business_unit_id', $businessUnitId)
            ->first();
        if ($bu) {
            HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->syncBusinessUnit($bu));
        }

        return $result;
    }
}
