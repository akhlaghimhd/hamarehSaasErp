<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\DTOs\CreateDepartmentDTO;
use App\Modules\Organization\DTOs\UpdateDepartmentDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Exception;

class DepartmentService
{
    public function createDepartment(CreateDepartmentDTO $dto): Department
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $branchExists = Branch::where('tenant_id', $tenantId)
            ->where('branch_id', $dto->branchId)
            ->exists();

        if (!$branchExists) {
            throw new Exception("شعبه نامعتبر است یا شما دسترسی به آن ندارید.");
        }

        $this->ensureScopeAccess('BRANCH', $dto->branchId);

        if (Department::where('tenant_id', $tenantId)
                      ->where('branch_id', $dto->branchId)
                      ->where('code', $dto->code)->exists()) {
            throw new Exception("کد دپارتمان وارد شده برای این شعبه قبلاً ثبت شده است.");
        }

        if ($dto->parentDepartmentId) {
            $parentExists = Department::where('tenant_id', $tenantId)
                ->where('department_id', $dto->parentDepartmentId)
                ->exists();

            if (!$parentExists) {
                throw new Exception("دپارتمان والد نامعتبر است.");
            }
        }

        return Department::create([
            'tenant_id'            => $tenantId,
            'branch_id'            => $dto->branchId,
            'parent_department_id' => $dto->parentDepartmentId,
            'code'                 => $dto->code,
            'name'                 => $dto->name,
            'manager_user_id'      => $dto->managerUserId,
            'is_active'            => $dto->isActive,
        ]);
    }

    public function getAllDepartments()
    {
        $query = Department::with(['branch', 'parent'])->orderBy('created_at', 'desc');

        $departmentReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('DEPARTMENT');

        if (!empty($departmentReferenceIds)) {
            $query->whereIn('department_id', $departmentReferenceIds);
        } else {
            $branchReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('BRANCH');
            if (!empty($branchReferenceIds)) {
                $query->whereIn('branch_id', $branchReferenceIds);
            }
        }

        return $query->get();
    }

    public function updateDepartment(string $departmentId, UpdateDepartmentDTO $dto): Department
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $department = Department::where('tenant_id', $tenantId)
            ->where('department_id', $departmentId)
            ->firstOrFail();

        $this->ensureScopeAccess('DEPARTMENT', $departmentId);

        // تعیین شعبه نهایی (اگر ارسال نشده، شعبه فعلی حفظ می‌شود)
        $targetBranchId = $dto->branchId ?? $department->branch_id;

        // اگر شعبه تغییر کرده، اعتبارسنجی امنیتی انجام شود
        if ($targetBranchId !== $department->branch_id) {
            $branchExists = Branch::where('tenant_id', $tenantId)
                ->where('branch_id', $targetBranchId)
                ->exists();

            if (!$branchExists) {
                throw new Exception("شعبه انتخاب شده نامعتبر است.");
            }

            $this->ensureScopeAccess('BRANCH', $targetBranchId);
        }

        // بررسی والد
        if ($dto->parentDepartmentId && $department->parent_department_id !== $dto->parentDepartmentId) {
            if ($dto->parentDepartmentId === $departmentId) {
                throw new Exception("یک دپارتمان نمی‌تواند والد خودش باشد.");
            }

            $parentExists = Department::where('tenant_id', $tenantId)
                ->where('department_id', $dto->parentDepartmentId)
                ->exists();

            if (!$parentExists) {
                throw new Exception("دپارتمان والد نامعتبر است.");
            }
        }

        // بررسی یکتا بودن کد درون شعبه هدف
        if ($department->code !== $dto->code || $department->branch_id !== $targetBranchId) {
            if (Department::where('tenant_id', $tenantId)
                          ->where('branch_id', $targetBranchId)
                          ->where('code', $dto->code)
                          ->where('department_id', '!=', $departmentId)
                          ->exists()) {
                throw new Exception("کد دپارتمان وارد شده برای این شعبه قبلاً ثبت شده است.");
            }
        }

        $department->update([
            'branch_id'            => $targetBranchId,
            'parent_department_id' => $dto->parentDepartmentId,
            'code'                 => $dto->code,
            'name'                 => $dto->name,
            'manager_user_id'      => $dto->managerUserId,
            'is_active'            => $dto->isActive,
        ]);

        return $department;
    }

    public function deleteDepartment(string $departmentId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $department = Department::where('tenant_id', $tenantId)
            ->where('department_id', $departmentId)
            ->firstOrFail();

        $this->ensureScopeAccess('DEPARTMENT', $departmentId);

        if ($department->children()->exists()) {
            throw new Exception("این دپارتمان دارای زیرمجموعه است و قابل حذف نیست.");
        }

        $department->delete();
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