<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\DTOs\CreateDepartmentDTO;
use App\Base\Context\TenantContext;
use Exception;

class DepartmentService
{
    public function createDepartment(CreateDepartmentDTO $dto): Department
    {
        $tenantId = TenantContext::getTenantId();

        // Security Check: بررسی مالکیت شعبه
        $branchExists = Branch::where('tenant_id', $tenantId)
            ->where('branch_id', $dto->branchId)
            ->exists();
            
        if (!$branchExists) {
            throw new Exception("شعبه نامعتبر است یا شما دسترسی به آن ندارید.");
        }

        // Security Check: اگر دپارتمان پدری انتخاب شده، باید متعلق به همین مستأجر باشد
        if ($dto->parentDepartmentId) {
            $parentExists = Department::where('tenant_id', $tenantId)
                ->where('department_id', $dto->parentDepartmentId)
                ->exists();
            if (!$parentExists) {
                throw new Exception("دپارتمان والد نامعتبر است.");
            }
        }

        // بررسی یکتا بودن کد دپارتمان درون همان شعبه
        if (Department::where('tenant_id', $tenantId)
                  ->where('branch_id', $dto->branchId)
                  ->where('code', $dto->code)->exists()) {
            throw new Exception("کد دپارتمان وارد شده برای این شعبه قبلاً ثبت شده است.");
        }

        return Department::create([
            'tenant_id' => $tenantId,
            'branch_id' => $dto->branchId,
            'parent_department_id' => $dto->parentDepartmentId,
            'code' => $dto->code,
            'name' => $dto->name,
            'manager_user_id' => $dto->managerUserId,
            'is_active' => $dto->isActive,
        ]);
    }

    public function getAllDepartments()
    {
        return Department::with(['branch', 'parent'])->orderBy('created_at', 'desc')->get();
    }
    public function updateDepartment(string $departmentId, \App\Modules\Organization\DTOs\UpdateDepartmentDTO $dto): Department
    {
        $tenantId = TenantContext::getTenantId();
        
        $department = Department::where('tenant_id', $tenantId)->where('department_id', $departmentId)->firstOrFail();

        // Security Check: بررسی اعتبار شعبه
        if ($department->branch_id !== $dto->branchId) {
            $branchExists = Branch::where('tenant_id', $tenantId)->where('branch_id', $dto->branchId)->exists();
            if (!$branchExists) {
                throw new Exception("شعبه انتخاب شده نامعتبر است.");
            }
        }

        // Security Check: بررسی اعتبار دپارتمان والد
        if ($dto->parentDepartmentId && $department->parent_department_id !== $dto->parentDepartmentId) {
            if ($dto->parentDepartmentId === $departmentId) {
                throw new Exception("یک دپارتمان نمی‌تواند والد خودش باشد.");
            }
            $parentExists = Department::where('tenant_id', $tenantId)->where('department_id', $dto->parentDepartmentId)->exists();
            if (!$parentExists) {
                throw new Exception("دپارتمان والد نامعتبر است.");
            }
        }

        // بررسی یکتا بودن کد درون شعبه
        if ($department->code !== $dto->code || $department->branch_id !== $dto->branchId) {
            if (Department::where('tenant_id', $tenantId)->where('branch_id', $dto->branchId)->where('code', $dto->code)->exists()) {
                throw new Exception("کد دپارتمان وارد شده برای این شعبه قبلاً ثبت شده است.");
            }
        }

        $department->update([
            'branch_id' => $dto->branchId,
            'parent_department_id' => $dto->parentDepartmentId,
            'code' => $dto->code,
            'name' => $dto->name,
            'manager_user_id' => $dto->managerUserId,
            'is_active' => $dto->isActive,
        ]);

        return $department;
    }

    public function deleteDepartment(string $departmentId): void
    {
        $tenantId = TenantContext::getTenantId();
        $department = Department::where('tenant_id', $tenantId)->where('department_id', $departmentId)->firstOrFail();
        
        if ($department->children()->exists()) {
            throw new Exception("این دپارتمان دارای زیرمجموعه است و قابل حذف نیست.");
        }

        $department->delete();
    }
}