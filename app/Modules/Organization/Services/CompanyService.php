<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use App\Base\Services\ScopeAccessGuard;

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
            throw new \Exception("کد شرکت وارد شده قبلاً در سیستم ثبت شده است.");
        }

        return Company::create([
            'tenant_id'            => $tenantId,
            'code'                 => $dto->code,
            'name'                 => $dto->name,
            'registration_number'  => $dto->registrationNumber,
            'economic_code'        => $dto->economicCode,
            'is_active'            => $dto->isActive,
        ]);
    }

    public function getAllCompanies()
    {
        $query = Company::query()->orderBy('created_at', 'desc');

        $companyReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('COMPANY');

        if (!empty($companyReferenceIds)) {
            $query->whereIn('company_id', $companyReferenceIds);
        }

        return $query->get();
    }

    public function updateCompany(string $companyId, \App\Modules\Organization\DTOs\UpdateCompanyDTO $dto): Company
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $company = Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('COMPANY', $companyId);

        if ($company->code !== $dto->code) {
            if (Company::where('tenant_id', $tenantId)->where('code', $dto->code)->exists()) {
                throw new \Exception("کد شرکت وارد شده قبلاً در سیستم ثبت شده است.");
            }
        }

        $company->update([
            'code'                 => $dto->code,
            'name'                 => $dto->name,
            'registration_number'  => $dto->registrationNumber,
            'economic_code'        => $dto->economicCode,
            'is_active'            => $dto->isActive,
        ]);

        return $company;
    }

    public function deleteCompany(string $companyId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $company = Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('COMPANY', $companyId);

        if ($company->branches()->exists()) {
            throw new \Exception("این شرکت دارای شعبه‌های زیرمجموعه است و قابل حذف نیست.");
        }

        $company->delete();
    }
}
