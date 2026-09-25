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

        return DB::transaction(function () use ($tenantId, $dto, $entityKind, $parentId, $isPrimary, $rateType) {
            if ($isPrimary) {
                $this->clearPrimaryFlags($tenantId);
            }

            return Company::create([
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
        });
    }

    public function getAllCompanies()
    {
        return Company::query()
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
            if (Company::where('tenant_id', $tenantId)->where('code', $dto->code)->exists()) {
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

        $chartOfAccountsId = $dto->chartOfAccountsIdProvided
            ? $dto->chartOfAccountsId
            : $company->chart_of_accounts_id;

        $rateType = $dto->defaultConsolRateTypeProvided
            ? $this->normalizeRateType($dto->defaultConsolRateType)
            : $company->default_consol_rate_type;

        if ($dto->defaultConsolRateTypeProvided) {
            $rateType = $this->normalizeRateType($dto->defaultConsolRateType);
        }

        $this->assertParentValid($tenantId, $parentId, $companyId);
        $this->assertEntityKindRules($entityKind, $parentId);
        $this->assertEliminationCurrency($tenantId, $entityKind, $parentId, $baseCurrencyId);

        if ($company->is_primary && $isPrimary === false) {
            $otherPrimary = Company::where('tenant_id', $tenantId)
                ->where('company_id', '!=', $companyId)
                ->where('is_primary', true)
                ->exists();
            if (!$otherPrimary) {
                throw new \Exception('حداقل یک شرکت اصلی (primary) برای سازمان الزامی است.');
            }
        }

        $becomingInactive = $dto->isActive === false && (bool) $company->is_active === true;
        if ($becomingInactive && $company->is_primary) {
            throw new \Exception('شرکت اصلی قابل غیرفعال‌سازی نیست. ابتدا شرکت دیگری را به‌عنوان اصلی تعیین کنید.');
        }

        return DB::transaction(function () use (
            $company,
            $tenantId,
            $dto,
            $entityKind,
            $parentId,
            $isPrimary,
            $baseCurrencyId,
            $chartOfAccountsId,
            $rateType,
            $becomingInactive
        ) {
            if ($isPrimary === true) {
                $this->clearPrimaryFlags($tenantId, $company->company_id);
            }

            $status = $dto->status;
            if ($dto->isActive === false && ($status === null || (int) $status === 1)) {
                $status = 2;
            }
            if ($dto->isActive === true && ($status === null || (int) $status === 2)) {
                $status = 1;
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
                'status'                    => $status,
                'is_primary'                => $isPrimary,
                'parent_company_id'         => $parentId,
                'entity_kind'               => $entityKind,
                'base_currency_id'          => $baseCurrencyId,
                'chart_of_accounts_id'      => $chartOfAccountsId,
                'default_consol_rate_type'  => $rateType,
                'row_version'               => ((int) ($company->row_version ?? 1)) + 1,
            ]);

            if ($becomingInactive) {
                $this->cascadeDeactivateSubtree($tenantId, (string) $company->company_id);
            }

            return $company->fresh();
        });
    }

    public function deleteCompany(string $companyId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $company = Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('COMPANY', $companyId);

        if ($company->branches()->exists()) {
            throw new \Exception('این شرکت دارای شعبه‌های زیرمجموعه است و قابل حذف نیست. ابتدا شعب را حذف یا منتقل کنید.');
        }

        if ($company->children()->exists()) {
            throw new \Exception('این شرکت دارای شرکت‌های زیرمجموعه است و قابل حذف نیست.');
        }

        if ($company->is_primary) {
            $otherCount = Company::where('tenant_id', $tenantId)
                ->where('company_id', '!=', $companyId)
                ->count();
            if ($otherCount > 0) {
                throw new \Exception('شرکت اصلی قابل حذف نیست. ابتدا شرکت دیگری را به‌عنوان اصلی تعیین کنید.');
            }
        }

        // Soft delete only — historical documents remain readable elsewhere; no hard delete.
        $company->delete();
    }

    public function ensurePrimaryCompanyForTenant(string $tenantId, ?string $name = null, string $code = 'HQ'): Company
    {
        $previous = TenantContext::getInstance()->getTenantId();
        TenantContext::getInstance()->setTenantId($tenantId);
        app()->instance('current_tenant_id', $tenantId);

        try {
            $existing = Company::where('tenant_id', $tenantId)
                ->where('is_primary', true)
                ->orderBy('created_at')
                ->first();

            if ($existing) {
                return $existing;
            }

            $any = Company::where('tenant_id', $tenantId)->orderBy('created_at')->first();
            if ($any) {
                DB::transaction(function () use ($tenantId, $any) {
                    $this->clearPrimaryFlags($tenantId);
                    $any->update([
                        'is_primary'  => true,
                        'entity_kind' => $any->entity_kind ?: Company::ENTITY_KIND_OPERATING,
                        'row_version' => ((int) ($any->row_version ?? 1)) + 1,
                    ]);
                });

                return $any->fresh();
            }

            $legalName = $name ?: 'شرکت اصلی';

            return Company::create([
                'tenant_id'    => $tenantId,
                'code'         => $code,
                'name'         => $legalName,
                'legal_name'   => $legalName,
                'is_active'    => true,
                'status'       => 1,
                'is_primary'   => true,
                'entity_kind'  => Company::ENTITY_KIND_OPERATING,
                'row_version'  => 1,
            ]);
        } finally {
            if ($previous) {
                TenantContext::getInstance()->setTenantId($previous);
                app()->instance('current_tenant_id', $previous);
            }
        }
    }

    /**
     * Propagate deactivation to descendant companies and their branches.
     * Historical documents stay readable; new operations should respect is_active via scope/UI.
     *
     * @return list<string> company ids deactivated (excluding root)
     */
    private function cascadeDeactivateSubtree(string $tenantId, string $rootCompanyId): array
    {
        $all = Company::where('tenant_id', $tenantId)
            ->get(['company_id', 'parent_company_id', 'is_active', 'is_primary']);

        $byParent = [];
        foreach ($all as $c) {
            $pid = $c->parent_company_id ? (string) $c->parent_company_id : '';
            $byParent[$pid][] = (string) $c->company_id;
        }

        $descendants = [];
        $queue = [$rootCompanyId];
        $guard = 0;
        while ($queue !== [] && $guard < 500) {
            $guard++;
            $current = array_shift($queue);
            foreach ($byParent[$current] ?? [] as $childId) {
                if (!in_array($childId, $descendants, true) && $childId !== $rootCompanyId) {
                    $descendants[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        $targets = array_values(array_unique(array_merge([$rootCompanyId], $descendants)));

        if ($targets !== []) {
            Company::where('tenant_id', $tenantId)
                ->whereIn('company_id', $targets)
                ->where('is_primary', false)
                ->update([
                    'is_active'   => false,
                    'status'      => 2,
                    'updated_at'  => now(),
                ]);

            Branch::where('tenant_id', $tenantId)
                ->whereIn('company_id', $targets)
                ->where('is_active', true)
                ->update([
                    'is_active'  => false,
                    'updated_at' => now(),
                ]);
        }

        return $descendants;
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
        $kind = $kind ? strtoupper(trim($kind)) : Company::ENTITY_KIND_OPERATING;
        if (!in_array($kind, Company::ENTITY_KINDS, true)) {
            throw new \Exception('نوع موجودیت شرکت نامعتبر است. مقادیر مجاز: OPERATING, CONSOLIDATION, ELIMINATION');
        }

        return $kind;
    }

    private function normalizeRateType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $type = strtoupper(trim($type));
        if (!in_array($type, Company::CONSOL_RATE_TYPES, true)) {
            throw new \Exception('نوع نرخ تسعیر نامعتبر است. مقادیر مجاز: CURRENT, AVERAGE, HISTORICAL');
        }

        return $type;
    }

    private function assertParentValid(string $tenantId, ?string $parentId, ?string $selfId): void
    {
        if ($parentId === null || $parentId === '') {
            return;
        }

        if ($selfId !== null && $parentId === $selfId) {
            throw new \Exception('شرکت نمی‌تواند والد خودش باشد.');
        }

        $parent = Company::where('tenant_id', $tenantId)
            ->where('company_id', $parentId)
            ->first();

        if (!$parent) {
            throw new \Exception('شرکت والد در این سازمان یافت نشد.');
        }

        if ($selfId !== null) {
            $cursor = $parentId;
            $guard = 0;
            while ($cursor !== null && $guard < 50) {
                if ($cursor === $selfId) {
                    throw new \Exception('ساختار سلسله‌مراتبی شرکت‌ها نمی‌تواند حلقه (cycle) داشته باشد.');
                }
                $cursor = Company::where('tenant_id', $tenantId)
                    ->where('company_id', $cursor)
                    ->value('parent_company_id');
                $guard++;
            }
        }
    }

    private function assertEntityKindRules(string $entityKind, ?string $parentId): void
    {
        if ($entityKind === Company::ENTITY_KIND_ELIMINATION && ($parentId === null || $parentId === '')) {
            throw new \Exception('شرکت از نوع ELIMINATION باید شرکت والد داشته باشد.');
        }
    }

    /**
     * ORG-P1-05 extension: elimination entity currency should match parent when both set.
     */
    private function assertEliminationCurrency(
        string $tenantId,
        string $entityKind,
        ?string $parentId,
        ?string $baseCurrencyId
    ): void {
        if ($entityKind !== Company::ENTITY_KIND_ELIMINATION || $parentId === null || $baseCurrencyId === null) {
            return;
        }

        $parentCurrency = Company::where('tenant_id', $tenantId)
            ->where('company_id', $parentId)
            ->value('base_currency_id');

        if ($parentCurrency !== null && $parentCurrency !== $baseCurrencyId) {
            throw new \Exception('ارز پایه شرکت ELIMINATION باید با شرکت والد یکسان باشد.');
        }
    }
}
