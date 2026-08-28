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

        $companyExists = Company::where('tenant_id', $tenantId)
            ->where('company_id', $dto->companyId)
            ->exists();

        if (!$companyExists) {
            throw new Exception("شرکت نامعتبر است یا شما دسترسی به آن ندارید.");
        }

        $this->ensureScopeAccess('COMPANY', $dto->companyId);

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

        $branchReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('BRANCH');

        if (!empty($branchReferenceIds)) {
            $query->whereIn('branch_id', $branchReferenceIds);
        } else {
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

        $this->ensureScopeAccess('BRANCH', $branchId);

        // تعیین شرکت نهایی (اگر ارسال نشده، شرکت فعلی حفظ می‌شود)
        $targetCompanyId = $dto->companyId ?? $branch->company_id;

        // اگر شرکت تغییر کرده، اعتبارسنجی امنیتی انجام شود
        if ($targetCompanyId !== $branch->company_id) {
            $companyExists = Company::where('tenant_id', $tenantId)
                ->where('company_id', $targetCompanyId)
                ->exists();

            if (!$companyExists) {
                throw new Exception("شرکت انتخاب شده نامعتبر است.");
            }

            $this->ensureScopeAccess('COMPANY', $targetCompanyId);
        }

        // بررسی یکتا بودن کد درون شرکت هدف
        if ($branch->code !== $dto->code || $branch->company_id !== $targetCompanyId) {
            if (Branch::where('tenant_id', $tenantId)
                      ->where('company_id', $targetCompanyId)
                      ->where('code', $dto->code)
                      ->where('branch_id', '!=', $branchId)
                      ->exists()) {
                throw new Exception("کد شعبه وارد شده برای این شرکت قبلاً ثبت شده است.");
            }
        }

        $branch->update([
            'company_id' => $targetCompanyId,
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