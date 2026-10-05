<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Modules\Organization\DTOs\UpdateCompanyDTO;
use App\Base\Context\TenantContext;
use App\Base\Services\ScopeAccessGuard;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
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

        // PLT-W1-02: additional companies require multi_company pack
        $existingCount = Company::where('tenant_id', $tenantId)->count();
        if ($existingCount >= 1) {
            app(FeatureCatalogService::class)->assertEnabled(
                $tenantId,
                FeatureCatalogService::CODE_MULTI_COMPANY
            );
        }

        if (Company::where('tenant_id', $tenantId)->where('code', $dto->code)->exists()) {
            throw new \Exception('کد شرکت وارد شده قبلاً در سیستم ثبت شده است.');
        }

        $entityKind = $this->normalizeEntityKind($dto->entityKind);
        $parentId = $dto->parentCompanyId;
        $isPrimary = (bool) $dto->isPrimary;
        $rateType = $this->normalizeRateType($dto->defaultConsolRateType);

        // ADR-ID-ORG-003 §3.2: after the platform-provisioned primary exists,
        // tenant cannot create a second root (parent-less) company.
        if ($existingCount >= 1) {
            if ($parentId === null || $parentId === '') {
                throw new \Exception(
                    'پس از ثبت شرکت اصلی، هر شرکت جدید باید شرکت والد داشته باشد.'
                );
            }
            // Tenant must not claim primary on additional companies.
            $isPrimary = false;
        }

        $this->assertParentValid($tenantId, $parentId, null);
        $this->assertEntityKindRules($entityKind, $parentId);
        $this->assertEliminationCurrency($tenantId, $entityKind, $parentId, $dto->baseCurrencyId);

        $hasPrimary = Company::where('tenant_id', $tenantId)->where('is_primary', true)->exists();
        if (!$hasPrimary && $existingCount === 0) {
            $isPrimary = true;
            $parentId = null;
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
            if (Company::where('tenant_id', $tenantId)
                ->where('code', $dto->code)
                ->where('company_id', '!=', $companyId)
                ->exists()) {
                throw new \Exception('کد شرکت وارد شده قبلاً در سیستم ثبت شده است.');
            }
        }

        $entityKind = $this->normalizeEntityKind(
            $dto->entityKind !== null && $dto->entityKind !== ''
                ? $dto->entityKind
                : $company->entity_kind
        );

        $parentId = $dto->parentCompanyIdProvided
            ? $dto->parentCompanyId
            : $company->parent_company_id;

        $isPrimary = $dto->isPrimary !== null
            ? (bool) $dto->isPrimary
            : (bool) $company->is_primary;

        $baseCurrencyId = $dto->baseCurrencyIdProvided
            ? $dto->baseCurrencyId
            : $company->base_currency_id;

        $chartId = $dto->chartOfAccountsIdProvided
            ? $dto->chartOfAccountsId
            : $company->chart_of_accounts_id;

        $rateType = $dto->defaultConsolRateTypeProvided
            ? $this->normalizeRateType($dto->defaultConsolRateType)
            : $company->default_consol_rate_type;

        // ADR-ID-ORG-003 §3.2: primary root is immutable for tenant callers.
        if ($company->is_primary) {
            if ($parentId !== null && $parentId !== '') {
                throw new \Exception(
                    'شرکت اصلی (ریشه) نمی‌تواند زیرمجموعهٔ شرکت دیگری شود. جابه‌جایی ریشه فقط توسط مالک پلتفرم مجاز است.'
                );
            }
            $parentId = null;
            $isPrimary = true;
        } else {
            // Non-primary must always keep a parent (no second root via update).
            if ($parentId === null || $parentId === '') {
                throw new \Exception(
                    'شرکت غیر اصلی باید شرکت والد داشته باشد. ایجاد ریشهٔ دوم مجاز نیست.'
                );
            }
            if ($isPrimary === true) {
                throw new \Exception(
                    'انتقال پرچم شرکت اصلی فقط توسط مالک پلتفرم مجاز است.'
                );
            }
            $isPrimary = false;
        }

        $this->assertParentValid($tenantId, $parentId, $companyId);
        $this->assertEntityKindRules($entityKind, $parentId);
        $this->assertEliminationCurrency($tenantId, $entityKind, $parentId, $baseCurrencyId);

        if ($company->is_primary && $isPrimary === false) {
            throw new \Exception('نمی‌توان پرچم شرکت اصلی را بدون تعیین جایگزین برداشت.');
        }

        // Primary company must stay active (product rule).
        if ($company->is_primary && $dto->isActive === false) {
            throw new \Exception('شرکت اصلی قابل غیرفعال‌سازی نیست.');
        }

        $company = DB::transaction(function () use (
            $company,
            $tenantId,
            $dto,
            $entityKind,
            $parentId,
            $isPrimary,
            $baseCurrencyId,
            $chartId,
            $rateType
        ) {
            if ($isPrimary === true) {
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
                'chart_of_accounts_id'      => $chartId,
                'default_consol_rate_type'  => $rateType,
                'row_version'               => ((int) ($company->row_version ?? 1)) + 1,
            ]);

            return $company->fresh();
        });

        HierarchySyncService::safe(function (HierarchySyncService $sync) use ($company) {
            $fresh = $company->fresh() ?? $company;
            $sync->syncCompany($fresh);
            $sync->syncBranchesForCompany($fresh->company_id);
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

        // Every company has an implicit HQ branch (product law). Soft-delete must
        // cascade departments → branches → company so delete is usable from UI.
        $branchIds = Branch::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->pluck('branch_id')
            ->all();

        DB::transaction(function () use ($company, $tenantId, $companyId, $branchIds) {
            if ($branchIds !== []) {
                Department::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($companyId, $branchIds) {
                        $q->where('company_id', $companyId)
                            ->orWhereIn('branch_id', $branchIds);
                    })
                    ->update([
                        'is_active'   => false,
                        'updated_at'  => now(),
                    ]);

                Department::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($companyId, $branchIds) {
                        $q->where('company_id', $companyId)
                            ->orWhereIn('branch_id', $branchIds);
                    })
                    ->delete();

                Branch::where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->update([
                        'is_active'  => false,
                        'updated_at' => now(),
                    ]);

                Branch::where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->delete();
            }

            $company->update([
                'is_active'   => false,
                'status'      => 2,
                'row_version' => ((int) ($company->row_version ?? 1)) + 1,
            ]);
            $this->cascadeDeactivateSubtree($tenantId, (string) $companyId);
            $company->delete();
        });

        HierarchySyncService::safe(function (HierarchySyncService $sync) use ($companyId, $branchIds) {
            foreach ($branchIds as $bid) {
                $sync->deactivateEntityNodes('BRANCH', (string) $bid);
            }
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

        // Restored non-primary companies must still hang under a valid parent.
        if (!$company->is_primary && ($company->parent_company_id === null || $company->parent_company_id === '')) {
            $rootId = Company::where('tenant_id', $tenantId)
                ->where('is_primary', true)
                ->value('company_id');
            if (!$rootId) {
                throw new \Exception('شرکت اصلی برای اتصال والد یافت نشد. با پشتیبانی پلتفرم تماس بگیرید.');
            }
            $company->parent_company_id = $rootId;
        }

        $company = DB::transaction(function () use ($company, $tenantId, $companyId) {
            $company->restore();
            $company->update([
                'is_active'         => false,
                'parent_company_id' => $company->parent_company_id,
                'row_version'       => ((int) ($company->row_version ?? 1)) + 1,
            ]);

            // Restore soft-deleted branches of this company (codes free after cascade delete).
            $trashedBranches = Branch::onlyTrashed()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->get();

            foreach ($trashedBranches as $branch) {
                $codeClash = Branch::where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->where('code', $branch->code)
                    ->exists();
                if ($codeClash) {
                    continue;
                }
                $branch->restore();
                $branch->update([
                    'is_active'   => false,
                    'row_version' => ((int) ($branch->row_version ?? 1)) + 1,
                ]);
            }

            $this->ensureDefaultBranchQuietly($companyId, $tenantId);

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
                    'is_primary'         => true,
                    'parent_company_id'  => null,
                    'row_version'        => ((int) ($any->row_version ?? 1)) + 1,
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
                'entity_kind' => 'OPERATING',
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

        Company::where('tenant_id', $tenantId)->whereIn('company_id', $ids)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
        Branch::where('tenant_id', $tenantId)->whereIn('company_id', $ids)->update([
            'is_active' => false,
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
        $seen = $selfId ? [$selfId] : [];
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
        if ($entityKind === 'ELIMINATION' && ($parentId === null || $parentId === '')) {
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
