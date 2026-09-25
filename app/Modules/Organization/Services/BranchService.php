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
            throw new Exception('شرکت نامعتبر است یا شما دسترسی به آن ندارید.');
        }

        $this->scopeAccessGuard->assertAccess('COMPANY', $dto->companyId);

        if (Branch::where('tenant_id', $tenantId)
                  ->where('company_id', $dto->companyId)
                  ->where('code', $dto->code)->exists()) {
            throw new Exception('کد شعبه وارد شده برای این شرکت قبلاً ثبت شده است.');
        }

        $branchKind = $this->normalizeBranchKind($dto->branchKind);
        $this->assertParentValid($tenantId, $dto->companyId, $dto->parentBranchId, null);

        return Branch::create([
            'tenant_id'              => $tenantId,
            'company_id'             => $dto->companyId,
            'code'                   => $dto->code,
            'name'                   => $dto->name,
            'address'                => $dto->address,
            'branch_kind'            => $branchKind,
            'parent_branch_id'       => $dto->parentBranchId,
            'default_warehouse_id'   => $dto->defaultWarehouseId,
            'supports_shipping'      => $dto->supportsShipping,
            'supports_receiving'     => $dto->supportsReceiving,
            'is_manufacturing_site'  => $dto->isManufacturingSite,
            'is_active'              => $dto->isActive,
            'row_version'            => 1,
        ]);
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

        if ($companyId !== null && $companyId !== '') {
            $query->where('company_id', $companyId);
        }

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

        return $branch->fresh();
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

        $branch->delete();
    }

    public function restoreBranch(string $branchId): Branch
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $branch = Branch::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $this->scopeAccessGuard->assertAccess('BRANCH', $branchId);

        // Code uniqueness among non-deleted rows of the same company
        if (Branch::where('tenant_id', $tenantId)
            ->where('company_id', $branch->company_id)
            ->where('code', $branch->code)
            ->exists()) {
            throw new Exception('کد این شعبه با یک شعبه فعال دیگر در همان شرکت تداخل دارد.');
        }

        $branch->restore();

        // Remain inactive after restore until explicit activation
        $branch->update([
            'is_active'   => false,
            'row_version' => ((int) ($branch->row_version ?? 1)) + 1,
        ]);

        return $branch->fresh();
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

        return Branch::create([
            'tenant_id'   => $tenantId,
            'company_id'  => $companyId,
            'code'        => 'HQ',
            'name'        => 'دفتر مرکزی',
            'branch_kind' => Branch::KIND_OFFICE,
            'is_active'   => true,
            'row_version' => 1,
        ]);
    }

    private function normalizeBranchKind(?string $kind): string
    {
        $kind = $kind ? strtoupper(trim($kind)) : Branch::KIND_OFFICE;
        if (!in_array($kind, Branch::BRANCH_KINDS, true)) {
            throw new Exception('نوع شعبه نامعتبر است. مقادیر مجاز: OFFICE, PLANT, WAREHOUSE_SITE, DISTRIBUTION, MIXED');
        }

        return $kind;
    }

    private function assertParentValid(
        string $tenantId,
        string $companyId,
        ?string $parentId,
        ?string $selfId
    ): void {
        if ($parentId === null || $parentId === '') {
            return;
        }

        if ($selfId !== null && $parentId === $selfId) {
            throw new Exception('شعبه نمی‌تواند والد خودش باشد.');
        }

        $parent = Branch::where('tenant_id', $tenantId)
            ->where('branch_id', $parentId)
            ->first();

        if (!$parent) {
            throw new Exception('شعبه والد یافت نشد.');
        }

        if ($parent->company_id !== $companyId) {
            throw new Exception('شعبه والد باید متعلق به همان شرکت باشد.');
        }

        if ($selfId !== null) {
            $cursor = $parentId;
            $guard = 0;
            while ($cursor !== null && $guard < 50) {
                if ($cursor === $selfId) {
                    throw new Exception('ساختار سلسله‌مراتبی شعبه‌ها نمی‌تواند حلقه (cycle) داشته باشد.');
                }
                $cursor = Branch::where('tenant_id', $tenantId)
                    ->where('branch_id', $cursor)
                    ->value('parent_branch_id');
                $guard++;
            }
        }
    }

    private function ensureScopeContextHydrated(): void
    {
        $ctx = ScopeContext::getInstance();

        if ($ctx->hasScopes()) {
            return;
        }

        $scopes = null;

        if (app()->bound('current_user_scopes')) {
            $scopes = app('current_user_scopes');
        }

        if (empty($scopes) || !is_array($scopes)) {
            return;
        }

        $tenantUserId = app()->bound('current_tenant_user_id')
            ? app('current_tenant_user_id')
            : null;

        $ctx->setScopes($scopes, $tenantUserId);
    }
}
