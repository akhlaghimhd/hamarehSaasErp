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
            throw new Exception("شرکت نامعتبر است یا شما دسترسی به آن ندارید.");
        }

        $this->scopeAccessGuard->assertAccess('COMPANY', $dto->companyId);

        if (Branch::where('tenant_id', $tenantId)
                  ->where('company_id', $dto->companyId)
                  ->where('code', $dto->code)->exists()) {
            throw new Exception("کد شعبه وارد شده برای این شرکت قبلاً ثبت شده است.");
        }

        return Branch::create([
            'tenant_id'   => $tenantId,
            'company_id'  => $dto->companyId,
            'code'        => $dto->code,
            'name'        => $dto->name,
            'address'     => $dto->address,
            'is_active'   => $dto->isActive,
            'row_version' => 1,
        ]);
    }

    /**
     * List branches for current tenant, filtered by:
     * 1. ScopeScoped global scope (BRANCH reference_ids when present)
     * 2. Explicit BRANCH scopes from ScopeContext (defense in depth)
     * 3. Fallback: COMPANY scopes → filter by company_id
     * 4. Optional route company filter (nested /companies/{company}/branches)
     *
     * @param  string|null  $companyId  Optional company filter from route
     */
    public function getAllBranches(?string $companyId = null)
    {
        $this->ensureScopeContextHydrated();

        $query = Branch::with('company')->orderBy('created_at', 'desc');

        if ($companyId !== null && $companyId !== '') {
            $query->where('company_id', $companyId);
        }

        $branchReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('BRANCH');

        if (!empty($branchReferenceIds)) {
            $query->whereIn('branch_id', $branchReferenceIds);
        } else {
            // No BRANCH scopes: fall back to COMPANY scopes (gradual-friendly)
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
                throw new Exception("شرکت انتخاب شده نامعتبر است.");
            }

            $this->scopeAccessGuard->assertAccess('COMPANY', $targetCompanyId);
        }

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
            'company_id'  => $targetCompanyId,
            'code'        => $dto->code,
            'name'        => $dto->name,
            'address'     => $dto->address,
            'is_active'   => $dto->isActive,
            'row_version' => ((int) ($branch->row_version ?? 1)) + 1,
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
            throw new Exception("این شعبه دارای دپارتمان‌های زیرمجموعه است و قابل حذف نیست.");
        }

        $branch->delete();
    }

    /**
     * If ScopeContext was reset/cleared but middleware already bound scopes
     * on the container, re-hydrate so list filters still apply.
     */
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
