<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;

class CompanyService
{
    /**
     * ایجاد شرکت جدید با رعایت دقیق کانتکست مستأجر
     */
    public function createCompany(CreateCompanyDTO $dto): Company
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        // بررسی یکتا بودن کد شرکت در سطح همان مستأجر
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

    /**
     * دریافت لیست شرکت‌ها با اعمال فیلتر Scope (در صورت وجود)
     */
    public function getAllCompanies()
    {
        $query = Company::query()->orderBy('created_at', 'desc');

        // اعمال فیلتر Scope در صورت وجود Scope از نوع COMPANY
        $companyReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('COMPANY');

        if (!empty($companyReferenceIds)) {
            $query->whereIn('company_id', $companyReferenceIds);
        }

        return $query->get();
    }

    /**
     * ویرایش اطلاعات شرکت با بررسی ایزوله‌سازی مستأجر و Scope
     */
    public function updateCompany(string $companyId, \App\Modules\Organization\DTOs\UpdateCompanyDTO $dto): Company
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $company = Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // بررسی دسترسی Scope
        $this->ensureScopeAccess('COMPANY', $companyId);

        // بررسی یکتا بودن کد جدید (در صورتی که کد تغییر کرده باشد)
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

    /**
     * حذف منطقی شرکت (Soft Delete)
     */
    public function deleteCompany(string $companyId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $company = Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // بررسی دسترسی Scope
        $this->ensureScopeAccess('COMPANY', $companyId);

        // نکته معماری: در صورت وجود شعبه (Branch) برای این شرکت، باید از حذف جلوگیری شود
        if ($company->branches()->exists()) {
            throw new \Exception("این شرکت دارای شعبه‌های زیرمجموعه است و قابل حذف نیست.");
        }

        $company->delete();
    }

    /**
     * بررسی دسترسی کاربر به یک reference_id خاص بر اساس Scope
     * اگر کاربر هیچ Scope از آن نوع نداشته باشد، دسترسی آزاد است (tenant-admin)
     */
    private function ensureScopeAccess(string $scopeType, string $referenceId): void
    {
        $scopeContext = ScopeContext::getInstance();
        $referenceIds = $scopeContext->getReferenceIdsByType($scopeType);

        // اگر کاربر Scope محدودکننده دارد و reference_id در لیستش نیست → دسترسی رد
        if (!empty($referenceIds) && !in_array($referenceId, $referenceIds, true)) {
            throw new \Exception("شما دسترسی لازم به این منبع را ندارید.");
        }
    }
}