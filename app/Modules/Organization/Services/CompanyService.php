<?php

namespace App\Modules\Organization\Services;

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

        $this->assertParentValid($tenantId, $parentId, null);
        $this->assertEntityKindRules($entityKind, $parentId);

        $hasPrimary = Company::where('tenant_id', $tenantId)->where('is_primary', true)->exists();
        if (!$hasPrimary) {
            $isPrimary = true;
        }

        return DB::transaction(function () use ($tenantId, $dto, $entityKind, $parentId, $isPrimary) {
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

        $this->assertParentValid($tenantId, $parentId, $companyId);
        $this->assertEntityKindRules($entityKind, $parentId);

        if ($company->is_primary && $isPrimary === false) {
            $otherPrimary = Company::where('tenant_id', $tenantId)
                ->where('company_id', '!=', $companyId)
                ->where('is_primary', true)
                ->exists();
            if (!$otherPrimary) {
                throw new \Exception('حداقل یک شرکت اصلی (primary) برای سازمان الزامی است.');
            }
        }

        return DB::transaction(function () use ($company, $tenantId, $dto, $entityKind, $parentId, $isPrimary) {
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
                'row_version'               => ((int) ($company->row_version ?? 1)) + 1,
            ]);

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
            throw new \Exception('این شرکت دارای شعبه‌های زیرمجموعه است و قابل حذف نیست.');
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

        $company->delete();
    }

    /**
     * ORG-P1-06 — Ensure HQ/primary operating company exists for a tenant (onboarding parity).
     */
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
}
