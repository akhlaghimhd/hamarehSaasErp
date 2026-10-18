<?php

namespace App\Modules\IdentityCore\Services;

use App\Base\Services\HoldingAccessService;
use App\Modules\IdentityCore\Catalog\RoleGrantTier;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ADR-ID-ORG-006 — hierarchical role grant ceiling.
 */
class RoleGrantCeilingService
{
    public function __construct(
        private readonly HoldingAccessService $holdingAccess
    ) {}

    public function actorGrantRank(): int
    {
        if ($this->holdingAccess->isGroupObserver() && ! $this->holdingAccess->isTechnicalOwner()) {
            return 0;
        }
        if ($this->holdingAccess->isIdentityMutateGroupWide() || $this->holdingAccess->isTechnicalOwner()) {
            return RoleGrantTier::rank(RoleGrantTier::TENANT);
        }
        if ($this->holdingAccess->isBranchOperator() && ! $this->holdingAccess->isCompanyOperator()) {
            if ($this->holdingAccess->actorCompanyScopeIds() === [] && $this->holdingAccess->actorBranchIds() !== []) {
                return RoleGrantTier::rank(RoleGrantTier::BRANCH);
            }
        }
        if ($this->holdingAccess->isCompanyOperator() || $this->holdingAccess->actorCompanyScopeIds() !== []) {
            return RoleGrantTier::rank(RoleGrantTier::COMPANY);
        }
        if ($this->holdingAccess->actorBranchIds() !== []) {
            return RoleGrantTier::rank(RoleGrantTier::BRANCH);
        }

        return RoleGrantTier::rank(RoleGrantTier::COMPANY);
    }

    public function targetCapacityRank(string $tenantId, string $userId): int
    {
        // withoutGlobalScopes: holding TenantUser scopes must not hide the target during capacity checks
        $tu = TenantUser::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if (! $tu) {
            return RoleGrantTier::rank(RoleGrantTier::BRANCH);
        }

        if (! empty($tu->is_owner)) {
            return RoleGrantTier::rank(RoleGrantTier::TENANT);
        }

        $scopes = DB::table('tenant_user_scopes as tus')
            ->join('tenant_scopes as s', 's.scope_id', '=', 'tus.scope_id')
            ->where('tus.tenant_id', $tenantId)
            ->where('tus.tenant_user_id', $tu->tenant_user_id)
            ->whereNull('tus.deleted_at')
            ->whereNull('s.deleted_at')
            ->get(['s.scope_type', 's.reference_id']);

        $companyIds = [];
        $hasBranch = false;
        foreach ($scopes as $row) {
            $type = strtoupper((string) $row->scope_type);
            $ref = $row->reference_id ? (string) $row->reference_id : '';
            if ($type === 'COMPANY' && $ref !== '') {
                $companyIds[] = $ref;
            }
            if ($type === 'BRANCH' && $ref !== '') {
                $hasBranch = true;
            }
        }

        if ($tu->home_company_id) {
            $companyIds[] = (string) $tu->home_company_id;
        }
        $companyIds = array_values(array_unique($companyIds));

        if ($companyIds === []) {
            return $hasBranch
                ? RoleGrantTier::rank(RoleGrantTier::BRANCH)
                : RoleGrantTier::rank(RoleGrantTier::COMPANY);
        }

        $companies = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->whereIn('company_id', $companyIds)
            ->whereNull('deleted_at')
            ->get(['company_id', 'parent_company_id', 'is_primary']);

        foreach ($companies as $co) {
            $isPrimary = ! empty($co->is_primary);
            $isRoot = $co->parent_company_id === null || $co->parent_company_id === '';
            if ($isPrimary || $isRoot) {
                return RoleGrantTier::rank(RoleGrantTier::GROUP);
            }
        }

        return RoleGrantTier::rank(RoleGrantTier::COMPANY);
    }

    /**
     * @param  list<string>  $roleIdsToAdd
     */
    public function assertCanGrantRoleIds(string $tenantId, string $targetUserId, array $roleIdsToAdd): void
    {
        $roleIdsToAdd = array_values(array_unique(array_filter($roleIdsToAdd)));
        if ($roleIdsToAdd === []) {
            return;
        }

        $actorRank = $this->actorGrantRank();
        if ($actorRank <= 0) {
            throw new RuntimeException('شما مجاز به تخصیص نقش نیستید.');
        }

        $targetRank = $this->targetCapacityRank($tenantId, $targetUserId);

        $roles = TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('tenant_role_id', $roleIdsToAdd)
            ->get(['tenant_role_id', 'code', 'name']);

        foreach ($roles as $role) {
            $tier = RoleGrantTier::tierForCode($role->code ?? null);
            $need = RoleGrantTier::rank($tier);
            $cap = min($actorRank, $targetRank);

            if ($need <= $cap) {
                continue;
            }

            if ($need > $actorRank) {
                throw new RuntimeException(
                    'این نقش خارج از سقف دسترسی شما برای اعطا است: '
                    .($role->name ?: $role->code)
                    .' (سطح '.RoleGrantTier::labelFa($tier).').'
                );
            }

            throw new RuntimeException(
                'برای اعطای این نقش، ابتدا باید اسکوپ سطح بالاتر (شرکت مادر یا گروه) به کاربر داده شود: '
                .($role->name ?: $role->code)
                .' (سطح '.RoleGrantTier::labelFa($tier).').'
            );
        }
    }
}
