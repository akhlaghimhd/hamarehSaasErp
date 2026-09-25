<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Modules\Organization\DTOs\UpdateCompanyDTO;
use App\Base\Context\TenantContext;
use App\Base\Services\ScopeAccessGuard;
use Illuminate\Support\Facades\DB;

class CompanyService
{
    public function __construct(
        protected ScopeAccessGuard $scopeAccessGuard = new ScopeAccessGuard()
    ) {
    }

    public function createCompany(CreateCompanyDTO $dto): Company
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (Company::where('tenant_id', $tenantId)->where('code', $dto->code)->exists()) {
            throw new \Exception('کد شرکت وارد شده قبلاً در سیستم ثبت شده است.');
        }

        $entityKind = $this->normalizeEntityKind($dto->entityKind);
        $parentId = $dto->parentCompanyId;
        $isPrimary = (bool) $dto->isPrimary;
        $rateType = $this->normalizeRateType($dto->defaultConsolRateType);

        $this->assertParentValid($tenantId, $parentId, null);
        $this->assertEntityKindRules($entityKind, $parentId);
        $this->assertEliminationCurrency($tenantId, $entityKind, $parentId, $dto->baseCurrencyId);

        $hasPrimary = Company::where('tenant_id', $tenantId)->where('is_primary', true)->exists();
        if (!$hasPrimary) {
            $isPrimary = true;
        }

        $company = DB::transaction(function () use ($tenantId, $dto, $entityKind, $parentId, $isPrimary, $rateType) {
            if ($isPrimary) {
                $this->clearPrimaryFlags($tenantId);
            }

            $company = Company::create([
                'tenant_id'                 => $tenantId,
                'code'                      => $dto->code,
                'name'                      => $dto->name,
                'legal_name'                => $dto->legalName ?? $dto->name,
                'trade_name'                => $dto->tradeName,
                'company_type'              => $dto->companyType,
                'registration_number'       => $dto->registrationNumber,
                'registration_date'         => $dto->registrationDate,
                'registration_place'        => $dto->registrationPlace,
                'incorporation_country_id'  => $dto->incorporationCountryId,
                'economic_code'             => $dto->economicCode,
                'tax_identifier'            => $dto->taxIdentifier,
                'national_id'               => $dto->nationalId,
                'vat_registration'          => $dto->vatRegistration,
                'is_active'                 => $dto->isActive,
                'status'                    => $dto->status,
                'is_primary'                => $isPrimary,
                'parent_company_id'         => $parentId,
                'entity_kind'               => $entityKind,
                'base_currency_id'          => $dto->baseCurrencyId,
                'chart_of_accounts_id'      => $dto->chartOfAccountsId,
                'default_consol_rate_type'  => $rateType,
                'row_version'               => 1,
            ]);

            $this->ensureDefaultBranchQuietly($company->company_id, $tenantId);

            return $company;
        });

        HierarchySyncService::safe(function (HierarchySyncService $sync) use ($company) {
            $sync->syncCompany($company);
            $sync->syncBranchesForCompany($company->company_id);
        });

        return $company;
    }

    /**
     * @param  bool  $onlyTrashed  when true, only soft-deleted companies
     */
    public function getAllCompanies(bool $onlyTrashed = false)
    {
        $q = Company::query()->withCount(['branches', 'departments', 'children']);

        if ($onlyTrashed) {
            $q->onlyTrashed();
        }

        return $q
            ->orderByDesc('is_primary')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function updateCompany(string $companyId, UpdateCompanyDTO $dto): Company
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $company = Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('COMPANY', $companyId);

        if ($company->code !== $dto->code) {
            if (Company::where('tenant_id', $tenantId)->where('code', $dto->code)->where('company_id', '!=', $companyId)->exists()) {
                throw new \Exception('کد شرکت وارد شده قبلاً در سیستم ثبت شده است.');
            }
        }

        $entityKind = $dto->entityKindProvided
            ? $this->normalizeEntityKind($dto->entityKind)
            : $company->entity_kind;

        $parentId = $dto->parentCompanyIdProvided
            ? $dto->parentCompanyId
            : $company->parent_company_id;

        $isPrimary = $dto->isPrimaryProvided
            ? (bool) $dto->isPrimary
            : (bool) $company->is_primary;

        $rateType = $dto->defaultConsolRateTypeProvided
            ? $this->normalizeRateType($dto->defaultConsolRateType)
            : $company->default_consol_rate_type;

        $baseCurrencyId = $dto->baseCurrencyIdProvided
            ? $dto->baseCurrencyId
            : $company->base_currency_id;

        $this->assertParentValid($tenantId, $parentId, $companyId);
        $this->assertEntityKindRules($entityKind, $parentId);
        $this->assertEliminationCurrency($tenantId, $entityKind, $parentId, $baseCurrencyId);

        $company = DB::transaction(function () use (
            $company,
            $tenantId,
            $dto,
            $entityKind,
            $parentId,
            $isPrimary,
            $rateType,
            $baseCurrencyId
        ) {
            if ($isPrimary) {
                $this->clearPrimaryFlags($tenantId, $company->company_id);
            }

            $company->update([
                'code'                      => $dto->code,
                'name'                      => $dto->name,
                'legal_name'                => $dto->legalName ?? $dto->name,
                'trade_name'                => $dto->tradeName,
                'company_type'              => $dto->companyType,
                'registration_number'       => $dto->registrationNumber,
                'registration_date'         => $dto->registrationDate,
                'registration_place'        => $dto->registrationPlace,
                'incorporation_country_id'  => $dto->incorporationCountryId,
                'economic_code'             => $dto->economicCode,
                'tax_identifier'            => $dto->taxIdentifier,
                'national_id'               => $dto->nationalId,
                'vat_registration'          => $dto->vatRegistration,
                'is_active'                 => $dto->isActive,
                'status'                    => $dto->status,
                'is_primary'                => $isPrimary,
                'parent_company_id'         => $parentId,
                'entity_kind'               => $entityKind,
                'base_currency_id'          => $baseCurrencyId,
                'chart_of_accounts_id'      => $dto->chartOfAccountsIdProvided
                    ? $dto->chartOfAccountsId
                    : $company->chart_of_accounts_id,
                'default_consol_rate_type'  => $rateType,
                'row_version'               => ((int) ($company->row_version ?? 1)) + 1,
            ]);

            return $company->fresh();
        });

        HierarchySyncService::safe(function (HierarchySyncService $sync) use ($company) {
            $sync->syncCompany($company->fresh() ?? $company);
            $sync->syncBranchesForCompany($company->company_id);
        });

        return $company->fresh() ?? $company;
    }

    public function deleteCompany(string $companyId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $company = Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('COMPANY', $companyId);

        if ($company->is_primary) {
            throw new \Exception('شرکت اصلی قابل حذف نیست.');
        }

        if ($company->children()->exists()) {
            throw new \Exception('این شرکت دارای زیرمجموعه است و قابل حذف نیست.');
        }

        DB::transaction(function () use ($company, $tenantId, $companyId) {
            $company->update([
                'is_active'   => false,
                'status'      => 2,
                'row_version' => ((int) ($company->row_version ?? 1)) + 1,
            ]);
            $this->cascadeDeactivateSubtree($tenantId, (string) $companyId);
            $company->delete();
        });

        HierarchySyncService::safe(function (HierarchySyncService $sync) use ($companyId) {
            $sync->deactivateEntityNodes('COMPANY', $companyId);
        });
    }

    public function restoreCompany(string $companyId): Company
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $company = Company::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('COMPANY', $companyId);

        if (Company::where('tenant_id', $tenantId)->where('code', $company->code)->exists()) {
            throw new \Exception('کد این شرکت با یک شرکت فعال دیگر تداخل دارد.');
        }

        $company = DB::transaction(function () use ($company, $tenantId, $companyId) {
            $company->restore();

            $payload = [
                'is_active'   => false,
                'row_version' => ((int) ($company->row_version ?? 1)) + 1,
            ];

            $company->update($payload);

            return $company->fresh();
        });

        HierarchySyncService::safe(function (HierarchySyncService $sync) use ($company) {
            $fresh = $company->fresh() ?? $company;
            $sync->syncCompany($fresh);
            $sync->syncBranchesForCompany($fresh->company_id);
        });

        return $company->fresh() ?? $company;
    }

    public function getCompanyById(string $companyId, bool $withTrashed = true): ?Company
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $q = Company::query()->where('tenant_id', $tenantId)->where('company_id', $companyId);

        if ($withTrashed) {
            $q->withTrashed();
        }

        return $q->first();
    }

    public function ensurePrimaryCompanyForTenant(string $tenantId, ?string $name = null, string $code = 'HQ'): Company
    {
        return DB::transaction(function () use ($tenantId, $name, $code) {
            $existing = Company::where('tenant_id', $tenantId)->where('is_primary', true)->orderBy('created_at')->first();
            if ($existing) {
                $this->ensureDefaultBranchQuietly($existing->company_id, $tenantId);

                return $existing;
            }

            $any = Company::where('tenant_id', $tenantId)->orderBy('created_at')->first();
            if ($any) {
                $this->clearPrimaryFlags($tenantId);
                $any->update([
                    'is_primary'  => true,
                    'row_version' => ((int) ($any->row_version ?? 1)) + 1,
                ]);
                $fresh = $any->fresh();
                $this->ensureDefaultBranchQuietly($fresh->company_id, $tenantId);

                return $fresh;
            }

            $created = Company::create([
                'tenant_id'   => $tenantId,
                'code'        => $code,
                'name'        => $name ?: 'شرکت اصلی',
                'legal_name'  => $name ?: 'شرکت اصلی',
                'is_active'   => true,
                'status'      => 1,
                'is_primary'  => true,
                'entity_kind' => Company::ENTITY_KIND_OPERATING,
                'row_version' => 1,
            ]);
            $this->ensureDefaultBranchQuietly($created->company_id, $tenantId);

            return $created;
        });
    }

    private function ensureDefaultBranchQuietly(string $companyId, string $tenantId): void
    {
        try {
            app(BranchService::class)->ensureDefaultHqBranchForCompany($companyId, $tenantId);
        } catch (\Throwable) {
            // non-blocking
        }
    }

    private function cascadeDeactivateSubtree(string $tenantId, string $rootCompanyId): array
    {
        $ids = [$rootCompanyId];
        $queue = [$rootCompanyId];
        while ($queue) {
            $current = array_shift($queue);
            $children = Company::where('tenant_id', $tenantId)
                ->where('parent_company_id', $current)
                ->pluck('company_id')
                ->all();
            foreach ($children as $cid) {
                if (!in_array($cid, $ids, true)) {
                    $ids[] = $cid;
                    $queue[] = $cid;
                }
            }
        }

        Company::where('tenant_id', $tenantId)
            ->whereIn('company_id', $ids)
            ->update([
                'is_active'  => false,
                'updated_at' => now(),
            ]);

        Branch::where('tenant_id', $tenantId)
            ->whereIn('company_id', $ids)
            ->update([
                'is_active'  => false,
                'updated_at' => now(),
            ]);

        return $ids;
    }

    private function clearPrimaryFlags(string $tenantId, ?string $exceptCompanyId = null): void
    {
        $q = Company::where('tenant_id', $tenantId)->where('is_primary', true);
        if ($exceptCompanyId) {
            $q->where('company_id', '!=', $exceptCompanyId);
        }
        $q->update(['is_primary' => false]);
    }

    private function normalizeEntityKind(?string $kind): string
    {
        $kind = strtoupper(trim((string) $kind));
        $allowed = ['OPERATING', 'CONSOLIDATION', 'ELIMINATION'];

        return in_array($kind, $allowed, true) ? $kind : 'OPERATING';
    }

    private function normalizeRateType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }
        $type = strtoupper(trim($type));
        $allowed = ['CURRENT', 'AVERAGE', 'HISTORICAL'];

        return in_array($type, $allowed, true) ? $type : null;
    }

    private function assertParentValid(string $tenantId, ?string $parentId, ?string $selfId): void
    {
        if (!$parentId) {
            return;
        }
        if ($selfId && $parentId === $selfId) {
            throw new \Exception('شرکت نمی‌تواند والد خودش باشد.');
        }
        $parent = Company::where('tenant_id', $tenantId)->where('company_id', $parentId)->first();
        if (!$parent) {
            throw new \Exception('شرکت والد نامعتبر است.');
        }
        // cycle check upward
        $seen = [$selfId];
        $cursor = $parentId;
        while ($cursor) {
            if (in_array($cursor, $seen, true)) {
                throw new \Exception('چرخه در سلسله‌مراتب شرکت‌ها مجاز نیست.');
            }
            $seen[] = $cursor;
            $cursor = Company::where('tenant_id', $tenantId)->where('company_id', $cursor)->value('parent_company_id');
        }
    }

    private function assertEntityKindRules(string $entityKind, ?string $parentId): void
    {
        if ($entityKind === 'ELIMINATION' && !$parentId) {
            throw new \Exception('شرکت حذف معاملات گروهی باید شرکت والد داشته باشد.');
        }
    }

    private function assertEliminationCurrency(
        string $tenantId,
        string $entityKind,
        ?string $parentId,
        ?string $baseCurrencyId
    ): void {
        if ($entityKind !== 'ELIMINATION' || !$parentId || !$baseCurrencyId) {
            return;
        }
        $parentCurrency = Company::where('tenant_id', $tenantId)
            ->where('company_id', $parentId)
            ->value('base_currency_id');
        if ($parentCurrency && $parentCurrency !== $baseCurrencyId) {
            throw new \Exception('ارز پایه شرکت حذف معاملات باید با شرکت والد یکسان باشد.');
        }
    }
}
