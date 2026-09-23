<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Modules\Organization\DTOs\UpdateCompanyDTO;
use App\Base\Context\TenantContext;
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
            throw new \Exception('کد شرکت وارد شده قبلاً در سیستم ثبت شده است.');
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
            'row_version'               => 1,
        ]);
    }

    public function getAllCompanies()
    {
        return Company::query()
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
            'row_version'               => ((int) ($company->row_version ?? 1)) + 1,
        ]);

        return $company->fresh();
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

        $company->delete();
    }
}
