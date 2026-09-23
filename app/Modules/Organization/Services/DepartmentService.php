<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\DTOs\CreateDepartmentDTO;
use App\Modules\Organization\DTOs\UpdateDepartmentDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use App\Base\Services\ScopeAccessGuard;
use Exception;

class DepartmentService
{
    public function __construct(
        protected ScopeAccessGuard $scopeAccessGuard = new ScopeAccessGuard()
    ) {
    }

    public function createDepartment(CreateDepartmentDTO $dto): Department
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $branch = Branch::where('tenant_id', $tenantId)
            ->where('branch_id', $dto->branchId)
            ->first();

        if (!$branch) {
            throw new Exception('شعبه نامعتبر است یا شما دسترسی به آن ندارید.');
        }

        $this->scopeAccessGuard->assertAccess('BRANCH', $dto->branchId);

        if (Department::where('tenant_id', $tenantId)
                      ->where('branch_id', $dto->branchId)
                      ->where('code', $dto->code)->exists()) {
            throw new Exception('کد دپارتمان وارد شده برای این شعبه قبلاً ثبت شده است.');
        }

        if ($dto->parentDepartmentId) {
            $parentExists = Department::where('tenant_id', $tenantId)
                ->where('department_id', $dto->parentDepartmentId)
                ->exists();

            if (!$parentExists) {
                throw new Exception('دپارتمان والد نامعتبر است.');
            }
        }

        return Department::create([
            'tenant_id'            => $tenantId,
            'company_id'           => $branch->company_id,
            'branch_id'            => $dto->branchId,
            'parent_department_id' => $dto->parentDepartmentId,
            'code'                 => $dto->code,
            'name'                 => $dto->name,
            'manager_user_id'      => $dto->managerUserId,
            'is_active'            => $dto->isActive,
            'row_version'          => 1,
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

        $this->scopeAccessGuard->assertAccess('BRANCH', $department->branch_id);

        $targetBranchId = $dto->branchId ?? $department->branch_id;

        $targetBranch = Branch::where('tenant_id', $tenantId)
            ->where('branch_id', $targetBranchId)
            ->first();

        if (!$targetBranch) {
            throw new Exception('شعبه انتخاب شده نامعتبر است.');
        }

        if ($targetBranchId !== $department->branch_id) {
            $this->scopeAccessGuard->assertAccess('BRANCH', $targetBranchId);
        }

        if ($dto->parentDepartmentId && $department->parent_department_id !== $dto->parentDepartmentId) {
            if ($dto->parentDepartmentId === $departmentId) {
                throw new Exception('یک دپارتمان نمی‌تواند والد خودش باشد.');
            }

            $parentExists = Department::where('tenant_id', $tenantId)
                ->where('department_id', $dto->parentDepartmentId)
                ->exists();

            if (!$parentExists) {
                throw new Exception('دپارتمان والد نامعتبر است.');
            }
        }

        if ($department->code !== $dto->code || $department->branch_id !== $targetBranchId) {
            if (Department::where('tenant_id', $tenantId)
                          ->where('branch_id', $targetBranchId)
                          ->where('code', $dto->code)
                          ->where('department_id', '!=', $departmentId)
                          ->exists()) {
                throw new Exception('کد دپارتمان وارد شده برای این شعبه قبلاً ثبت شده است.');
            }
        }

        $department->update([
            'company_id'           => $targetBranch->company_id,
            'branch_id'            => $targetBranchId,
            'parent_department_id' => $dto->parentDepartmentId,
            'code'                 => $dto->code,
            'name'                 => $dto->name,
            'manager_user_id'      => $dto->managerUserId,
            'is_active'            => $dto->isActive,
            'row_version'          => ((int) ($department->row_version ?? 1)) + 1,
        ]);

        return $department->fresh();
    }

    public function deleteDepartment(string $departmentId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $department = Department::where('tenant_id', $tenantId)
            ->where('department_id', $departmentId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('BRANCH', $department->branch_id);

        if ($department->children()->exists()) {
            throw new Exception('این دپارتمان دارای زیرمجموعه است و قابل حذف نیست.');
        }

        $department->delete();
    }
}
