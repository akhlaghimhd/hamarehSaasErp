<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\DTOs\CreateBranchDTO;
use App\Base\Context\TenantContext;
use Exception;

class BranchService
{
    public function createBranch(CreateBranchDTO $dto): Branch
    {
        $tenantId = TenantContext::getTenantId();

        // Security Check: آیا شرکت مبدأ متعلق به همین مستأجر است؟
        $companyExists = Company::where('tenant_id', $tenantId)
            ->where('company_id', $dto->companyId)
            ->exists();
            
        if (!$companyExists) {
            throw new Exception("شرکت نامعتبر است یا شما دسترسی به آن ندارید.");
        }

        // بررسی یکتا بودن کد شعبه درون همان شرکت
        if (Branch::where('tenant_id', $tenantId)
                  ->where('company_id', $dto->companyId)
                  ->where('code', $dto->code)->exists()) {
            throw new Exception("کد شعبه وارد شده برای این شرکت قبلاً ثبت شده است.");
        }

        return Branch::create([
            'tenant_id' => $tenantId,
            'company_id' => $dto->companyId,
            'code' => $dto->code,
            'name' => $dto->name,
            'address' => $dto->address,
            'is_active' => $dto->isActive,
        ]);
    }

    public function getAllBranches()
    {
        return Branch::with('company')->orderBy('created_at', 'desc')->get();
    }
    public function updateBranch(string $branchId, \App\Modules\Organization\DTOs\UpdateBranchDTO $dto): Branch
    {
        $tenantId = TenantContext::getTenantId();
        
        $branch = Branch::where('tenant_id', $tenantId)->where('branch_id', $branchId)->firstOrFail();

        // Security Check: بررسی اعتبار شرکت جدید (در صورت تغییر)
        if ($branch->company_id !== $dto->companyId) {
            $companyExists = Company::where('tenant_id', $tenantId)->where('company_id', $dto->companyId)->exists();
            if (!$companyExists) {
                throw new Exception("شرکت انتخاب شده نامعتبر است.");
            }
        }

        // بررسی یکتا بودن کد درون شرکت (در صورت تغییر کد)
        if ($branch->code !== $dto->code || $branch->company_id !== $dto->companyId) {
            if (Branch::where('tenant_id', $tenantId)->where('company_id', $dto->companyId)->where('code', $dto->code)->exists()) {
                throw new Exception("کد شعبه وارد شده برای این شرکت قبلاً ثبت شده است.");
            }
        }

        $branch->update([
            'company_id' => $dto->companyId,
            'code' => $dto->code,
            'name' => $dto->name,
            'address' => $dto->address,
            'is_active' => $dto->isActive,
        ]);

        return $branch;
    }

    public function deleteBranch(string $branchId): void
    {
        $tenantId = TenantContext::getTenantId();
        $branch = Branch::where('tenant_id', $tenantId)->where('branch_id', $branchId)->firstOrFail();
        
        if ($branch->departments()->exists()) {
            throw new Exception("این شعبه دارای دپارتمان‌های زیرمجموعه است و قابل حذف نیست.");
        }

        $branch->delete();
    }
}