<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\DTOs\CreateBranchDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Exception;

class BranchService
{
    public function createBranch(CreateBranchDTO $dto): Branch
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        // Security Check: آیا شرکت مبدأ متعلق به همین مستأجر است؟
        $companyExists = Company::where('tenant_id', $tenantId)
            ->where('company_id', $dto->companyId)
            ->exists();

        if (!$companyExists) {
            throw new Exception("شرکت نامعتبر است یا شما دسترسی به آن ندارید.");
        }

        // بررسی دسترسی Scope به شرکت
        $this->ensureScopeAccess('COMPANY', $dto->companyId);

        // بررسی یکتا بودن کد شعبه درون همان شرکت
        if (Branch::where('tenant_id', $tenantId)
                  ->where('company_id', $dto->companyId)
                  ->where('code', $dto->code)->exists()) {
            throw new Exception("کد شعبه وارد شده برای این شرکت قبلاً ثبت شده است.");
        }

        return Branch::create([
            'tenant_id'  => $tenantId,
            'company_id' => $dto->companyId,
            'code'       => $dto->code,
            'name'       => $dto->name,
            'address'    => $dto->address,
            'is_active'  => $dto->isActive,
        ]);
    }

    public function getAllBranches()
    {
        $query = Branch::with('company')->orderBy('created_at', 'desc');

        // فیلتر بر اساس Scope از نوع BRANCH
        $branchReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('BRANCH');

        if (!empty($branchReferenceIds)) {
            $query->whereIn('branch_id', $branchReferenceIds);
        } else {
            // اگر Scope شعبه ندارد، حداقل بر اساس Scope شرکت فیلتر کن
            $companyReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('COMPANY');
            if (!empty($companyReferenceIds)) {
                $query->whereIn('company_id', $companyReferenceIds);
            }
        }

        return $query->get();
    }

    public function updateBranch(string $branchId, \App\Modules\Organization\DTOs\UpdateBranchDTO $dto): Branch
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $branch = Branch::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        // بررسی دسترسی Scope
        $this->ensureScopeAccess('BRANCH', $branchId);

        // Security Check: بررسی اعتبار شرکت جدید (در صورت تغییر)
        if ($branch->company_id !== $dto->companyId) {
            $companyExists = Company::where('tenant_id', $tenantId)
                ->where('company_id', $dto->companyId)
                ->exists();

            if (!$companyExists) {
                throw new Exception("شرکت انتخاب شده نامعتبر است.");
            }

            $this->ensureScopeAccess('COMPANY', $dto->companyId);
        }

        // بررسی یکتا بودن کد درون شرکت (در صورت تغییر کد)
        if ($branch->code !== $dto->code || $branch->company_id !== $dto->companyId) {
            if (Branch::where('tenant_id', $tenantId)
                      ->where('company_id', $dto->companyId)
                      ->where('code', $dto->code)->exists()) {
                throw new Exception("کد شعبه وارد شده برای این شرکت قبلاً ثبت شده است.");
            }
        }

        $branch->update([
            'company_id' => $dto->companyId,
            'code'       => $dto->code,
            'name'       => $dto->name,
            'address'    => $dto->address,
            'is_active'  => $dto->isActive,
        ]);

        return $branch;
    }

    public function deleteBranch(string $branchId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $branch = Branch::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        // بررسی دسترسی Scope
        $this->ensureScopeAccess('BRANCH', $branchId);

        if ($branch->departments()->exists()) {
            throw new Exception("این شعبه دارای دپارتمان‌های زیرمجموعه است و قابل حذف نیست.");
        }

        $branch->delete();
    }

    private function ensureScopeAccess(string $scopeType, string $referenceId): void
    {
        $scopeContext = ScopeContext::getInstance();
        $referenceIds = $scopeContext->getReferenceIdsByType($scopeType);

        if (!empty($referenceIds) && !in_array($referenceId, $referenceIds, true)) {
            throw new Exception("شما دسترسی لازم به این منبع را ندارید.");
        }
    }
}