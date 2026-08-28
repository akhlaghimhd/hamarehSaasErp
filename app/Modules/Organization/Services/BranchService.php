<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\DTOs\CreateBranchDTO;
use App\Modules\Organization\DTOs\UpdateBranchDTO;
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

        // اعمال فیلتر Scope در صورت وجود Scope از نوع BRANCH
        $branchReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('BRANCH');

        if (!empty($branchReferenceIds)) {
            $query->whereIn('branch_id', $branchReferenceIds);
        } else {
            // اگر Scope از نوع COMPANY وجود دارد، شعبه‌های همان شرکت‌ها را محدود کن
            $companyReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('COMPANY');
            if (!empty($companyReferenceIds)) {
                $query->whereIn('company_id', $companyReferenceIds);
            }
        }

        return $query->get();
    }

    public function updateBranch(string $branchId, UpdateBranchDTO $dto): Branch
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $branch = Branch::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        // بررسی دسترسی Scope
        $this->ensureScopeAccess('BRANCH', $branchId);

        // بررسی یکتا بودن کد درون همان شرکت (در صورت تغییر کد)
        if ($branch->code !== $dto->code) {
            if (Branch::where('tenant_id', $tenantId)
                      ->where('company_id', $branch->company_id)
                      ->where('code', $dto->code)
                      ->where('branch_id', '!=', $branchId)
                      ->exists()) {
                throw new Exception("کد شعبه وارد شده برای این شرکت قبلاً ثبت شده است.");
            }
        }

        $branch->update([
            'code'      => $dto->code,
            'name'      => $dto->name,
            'address'   => $dto->address,
            'is_active' => $dto->isActive,
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