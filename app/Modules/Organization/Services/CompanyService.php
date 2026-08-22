<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Base\Context\TenantContext;

class CompanyService
{
    /**
     * ایجاد شرکت جدید با رعایت دقیق کانتکست مستأجر
     */
    public function createCompany(CreateCompanyDTO $dto): Company
    {
        $tenantId = TenantContext::getTenantId();

        // بررسی یکتا بودن کد شرکت در سطح همان مستأجر
        if (Company::where('tenant_id', $tenantId)->where('code', $dto->code)->exists()) {
            throw new \Exception("کد شرکت وارد شده قبلاً در سیستم ثبت شده است.");
        }

        return Company::create([
            'tenant_id' => $tenantId,
            'code' => $dto->code,
            'name' => $dto->name,
            'registration_number' => $dto->registrationNumber,
            'economic_code' => $dto->economicCode,
            'is_active' => $dto->isActive,
        ]);
    }

    /**
     * دریافت لیست شرکت‌ها (لایه TenantScoped در مدل، داده‌های بقیه را فیلتر می‌کند)
     */
    public function getAllCompanies()
    {
        return Company::orderBy('created_at', 'desc')->get();
    }
    /**
     * ویرایش اطلاعات شرکت با بررسی ایزوله‌سازی مستأجر
     */
    public function updateCompany(string $companyId, \App\Modules\Organization\DTOs\UpdateCompanyDTO $dto): Company
    {
        $tenantId = TenantContext::getTenantId();
        
        // پیدا کردن شرکت با اطمینان از تعلق آن به مستأجر فعلی
        $company = Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        // بررسی یکتا بودن کد جدید (در صورتی که کد تغییر کرده باشد)
        if ($company->code !== $dto->code) {
            if (Company::where('tenant_id', $tenantId)->where('code', $dto->code)->exists()) {
                throw new \Exception("کد شرکت وارد شده قبلاً در سیستم ثبت شده است.");
            }
        }

        $company->update([
            'code' => $dto->code,
            'name' => $dto->name,
            'registration_number' => $dto->registrationNumber,
            'economic_code' => $dto->economicCode,
            'is_active' => $dto->isActive,
        ]);

        return $company;
    }

    /**
     * حذف منطقی شرکت (Soft Delete)
     */
    public function deleteCompany(string $companyId): void
    {
        $tenantId = TenantContext::getTenantId();
        $company = Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();
        
        // نکته معماری: در صورت وجود شعبه (Branch) برای این شرکت، باید از حذف جلوگیری شود
        if ($company->branches()->exists()) {
            throw new \Exception("این شرکت دارای شعبه‌های زیرمجموعه است و قابل حذف نیست.");
        }

        $company->delete();
    }
}