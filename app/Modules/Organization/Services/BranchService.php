<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\DTOs\CreateBranchDTO;
use App\Modules\Organization\DTOs\UpdateBranchDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use App\Base\Services\ScopeAccessGuard;
use Exception;

class BranchService
{
    public function __construct(
        protected ScopeAccessGuard $scopeAccessGuard = new ScopeAccessGuard()
    ) {
    }

    public function createBranch(CreateBranchDTO $dto): Branch
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $companyExists = Company::where('tenant_id', $tenantId)
            ->where('company_id', $dto->companyId)
            ->exists();

        if (!$companyExists) {
            throw new Exception('شرکت انتخاب شده نامعتبر است.');
        }

        $this->scopeAccessGuard->assertAccess('COMPANY', $dto->companyId);

        if (Branch::where('tenant_id', $tenantId)
            ->where('company_id', $dto->companyId)
            ->where('code', $dto->code)
            ->exists()) {
            throw new Exception('کد شعبه وارد شده برای این شرکت قبلاً ثبت شده است.');
        }

        $branchKind = $this->normalizeBranchKind($dto->branchKind);
        $this->assertParentValid($tenantId, $dto->companyId, $dto->parentBranchId, null);

        $branch = Branch::create([
            'tenant_id'              => $tenantId,
            'company_id'             => $dto->companyId,
            'code'                   => $dto->code,
            'name'                   => $dto->name,
            'address'                => $dto->address,
            'branch_kind'            => $branchKind,
            'parent_branch_id'       => $dto->parentBranchId,
            'default_warehouse_id'   => $dto->defaultWarehouseId,
            'supports_shipping'      => (bool) $dto->supportsShipping,
            'supports_receiving'     => (bool) $dto->supportsReceiving,
            'is_manufacturing_site'  => (bool) $dto->isManufacturingSite,
            'is_active'              => $dto->isActive,
            'row_version'            => 1,
        ]);

        HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->syncBranch($branch));

        return $branch;
    }

    /**
     * @param  bool  $onlyTrashed  when true, only soft-deleted branches
     */
    public function getAllBranches(?string $companyId = null, bool $onlyTrashed = false)
    {
        $this->ensureScopeContextHydrated();

        $query = Branch::query()->with('company')->orderBy('created_at', 'desc');

        if ($onlyTrashed) {
            $query->onlyTrashed();
        }

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $companyReferenceIds = ScopeContext::getInstance()->getAccessibleCompanyIds();
        if (!empty($companyReferenceIds)) {
            $query->whereIn('company_id', $companyReferenceIds);
        }

        return $query->get();
    }

    public function updateBranch(string $branchId, UpdateBranchDTO $dto): Branch
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $branch = Branch::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('BRANCH', $branchId);

        $targetCompanyId = $dto->companyId ?? $branch->company_id;

        if ($targetCompanyId !== $branch->company_id) {
            $companyExists = Company::where('tenant_id', $tenantId)
                ->where('company_id', $targetCompanyId)
                ->exists();

            if (!$companyExists) {
                throw new Exception('شرکت انتخاب شده نامعتبر است.');
            }

            $this->scopeAccessGuard->assertAccess('COMPANY', $targetCompanyId);
        }

        if ($branch->code !== $dto->code || $branch->company_id !== $targetCompanyId) {
            if (Branch::where('tenant_id', $tenantId)
                      ->where('company_id', $targetCompanyId)
                      ->where('code', $dto->code)
                      ->where('branch_id', '!=', $branchId)
                      ->exists()) {
                throw new Exception('کد شعبه وارد شده برای این شرکت قبلاً ثبت شده است.');
            }
        }

        $branchKind = $dto->branchKindProvided
            ? $this->normalizeBranchKind($dto->branchKind)
            : $branch->branch_kind;

        $parentId = $dto->parentBranchIdProvided
            ? $dto->parentBranchId
            : $branch->parent_branch_id;

        $defaultWh = $dto->defaultWarehouseIdProvided
            ? $dto->defaultWarehouseId
            : $branch->default_warehouse_id;

        $this->assertParentValid($tenantId, $targetCompanyId, $parentId, $branchId);

        $branch->update([
            'company_id'             => $targetCompanyId,
            'code'                   => $dto->code,
            'name'                   => $dto->name,
            'address'                => $dto->address,
            'branch_kind'            => $branchKind,
            'parent_branch_id'       => $parentId,
            'default_warehouse_id'   => $defaultWh,
            'supports_shipping'      => $dto->supportsShipping !== null
                ? $dto->supportsShipping
                : (bool) $branch->supports_shipping,
            'supports_receiving'     => $dto->supportsReceiving !== null
                ? $dto->supportsReceiving
                : (bool) $branch->supports_receiving,
            'is_manufacturing_site'  => $dto->isManufacturingSite !== null
                ? $dto->isManufacturingSite
                : (bool) $branch->is_manufacturing_site,
            'is_active'              => $dto->isActive,
            'row_version'            => ((int) ($branch->row_version ?? 1)) + 1,
        ]);

        $fresh = $branch->fresh() ?? $branch;

        HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->syncBranch($fresh));

        return $fresh;
    }

    public function deleteBranch(string $branchId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $branch = Branch::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('BRANCH', $branchId);

        if ($branch->departments()->exists()) {
            throw new Exception('این شعبه دارای دپارتمان‌های زیرمجموعه است و قابل حذف نیست.');
        }

        if ($branch->children()->exists()) {
            throw new Exception('این شعبه دارای شعبه‌های زیرمجموعه است و قابل حذف نیست.');
        }

        $branchIdStr = (string) $branch->branch_id;
        $branch->delete();

        HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->deactivateEntityNodes('BRANCH', $branchIdStr));
    }

    public function restoreBranch(string $branchId): Branch
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $branch = Branch::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('BRANCH', $branchId);

        if (Branch::where('tenant_id', $tenantId)
            ->where('company_id', $branch->company_id)
            ->where('code', $branch->code)
            ->exists()) {
            throw new Exception('کد این شعبه با یک شعبه فعال دیگر در همان شرکت تداخل دارد.');
        }

        $branch->restore();

        $branch->update([
            'is_active'   => false,
            'row_version' => ((int) ($branch->row_version ?? 1)) + 1,
        ]);

        $fresh = $branch->fresh() ?? $branch;
        HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->syncBranch($fresh));

        return $fresh;
    }

    /**
     * Product law (§9): every company gets at least one implicit HQ branch
     * so single-site tenants can use departments without multi-branch UX.
     */
    public function ensureDefaultHqBranchForCompany(string $companyId, ?string $tenantId = null): Branch
    {
        $tenantId = $tenantId ?: TenantContext::getInstance()->getTenantId();

        $company = Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->first();

        if (!$company) {
            throw new Exception('شرکت یافت نشد.');
        }

        $existing = Branch::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->orderBy('created_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $branch = Branch::create([
            'tenant_id'   => $tenantId,
            'company_id'  => $companyId,
            'code'        => 'HQ',
            'name'        => 'دفتر مرکزی',
            'branch_kind' => Branch::KIND_OFFICE,
            'is_active'   => true,
            'row_version' => 1,
        ]);

        HierarchySyncService::safe(fn (HierarchySyncService $sync) => $sync->syncBranch($branch));

        return $branch;
    }

    private function normalizeBranchKind(?string $kind): string
    {
        $kind = strtoupper(trim((string) $kind));
        $allowed = [
            Branch::KIND_OFFICE,
            Branch::KIND_PLANT,
            Branch::KIND_WAREHOUSE_SITE,
            Branch::KIND_DISTRIBUTION,
            Branch::KIND_MIXED,
        ];

        return in_array($kind, $allowed, true) ? $kind : Branch::KIND_OFFICE;
    }

    private function assertParentValid(
        string $tenantId,
        string $companyId,
        ?string $parentId,
        ?string $selfId
    ): void {
        if (!$parentId) {
            return;
        }
        if ($selfId && $parentId === $selfId) {
            throw new Exception('شعبه نمی‌تواند والد خودش باشد.');
        }
        $parent = Branch::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('branch_id', $parentId)
            ->first();
        if (!$parent) {
            throw new Exception('شعبه والد نامعتبر است یا به شرکت دیگری تعلق دارد.');
        }
    }

    private function ensureScopeContextHydrated(): void
    {
        // no-op placeholder for BC with callers that expect hydration side-effects
    }
}
