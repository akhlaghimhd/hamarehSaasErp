<?php

namespace App\Base\Services;

use App\Base\Context\ScopeContext;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ADR-ID-ORG-003 — Holding Access Model & Delegated Administration.
 *
 * - Tenant owner / group-wide actor (no COMPANY scopes): full tenant member visibility.
 * - Actor with COMPANY scopes: may only list/manage members whose COMPANY scopes intersect.
 * - Optional company_id filter further narrows (parent per-company view).
 * - Gradual-safe: empty COMPANY scopes keeps current SME behaviour (no extra filter).
 */
class HoldingAccessService
{
    public function __construct(
        protected ?ScopeContext $scopeContext = null
    ) {
        $this->scopeContext = $scopeContext ?? ScopeContext::getInstance();
    }

    public function isGroupWideActor(): bool
    {
        if ($this->currentUserIsTenantOwner()) {
            return true;
        }

        $companyScopes = $this->scopeContext->getScopesByType('COMPANY');

        return empty($companyScopes);
    }

    /**
     * @return list<string>
     */
    public function actorCompanyIds(): array
    {
        return array_values(array_unique(array_filter(
            $this->scopeContext->getReferenceIdsByType('COMPANY')
        )));
    }

    /**
     * Scope-ids (COMPANY type) the actor may grant to new members.
     *
     * @return list<string>
     */
    public function actorCompanyScopeIds(): array
    {
        $ids = [];
        foreach ($this->scopeContext->getScopesByType('COMPANY') as $scope) {
            if (!empty($scope['scope_id'])) {
                $ids[] = (string) $scope['scope_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Apply delegated-admin filter to a TenantUser query.
     *
     * @param  Builder<\App\Modules\IdentityCore\Models\TenantUser>  $query
     */
    public function constrainTenantUsersQuery(Builder $query, ?string $companyIdFilter = null): Builder
    {
        $tenantId = $this->resolveTenantId($query);

        if ($companyIdFilter !== null && $companyIdFilter !== '') {
            if (!$this->isGroupWideActor()) {
                $allowed = $this->actorCompanyIds();
                if (!in_array($companyIdFilter, $allowed, true)) {
                    $query->whereRaw('1 = 0');

                    return $query;
                }
            }

            return $this->whereTenantUserInCompanies($query, $tenantId, [$companyIdFilter]);
        }

        if ($this->isGroupWideActor()) {
            return $query;
        }

        $companyIds = $this->actorCompanyIds();

        return $this->whereTenantUserInCompanies($query, $tenantId, $companyIds);
    }

    public function assertCanManageTenantUser(string $tenantUserId): void
    {
        if ($this->isGroupWideActor()) {
            return;
        }

        $companyIds = $this->actorCompanyIds();
        if ($companyIds === []) {
            return;
        }

        $tenantId = $this->currentTenantId();
        $exists = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->where(function ($q) use ($tenantId, $companyIds) {
                $this->whereTenantUserInCompanies($q, $tenantId, $companyIds);
            })
            ->exists();

        if (!$exists) {
            throw new RuntimeException('دسترسی به این عضو در محدوده شرکت شما مجاز نیست.');
        }
    }

    /**
     * Resolve membership by platform user_id then apply company-scope guard.
     * Used by role assign (AssignRoleToUserDTO carries user_id, not tenant_user_id).
     */
    public function assertCanManageUserId(string $userId): void
    {
        if ($this->isGroupWideActor()) {
            return;
        }

        $tenantId = $this->currentTenantId();
        $tu = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if (!$tu) {
            throw new RuntimeException('کاربر سازمان یافت نشد.');
        }

        $this->assertCanManageTenantUser((string) $tu->tenant_user_id);
    }

    public function canManageTenantUser(string $tenantUserId): bool
    {
        try {
            $this->assertCanManageTenantUser($tenantUserId);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @param  list<string>  $companyIds
     */
    protected function whereTenantUserInCompanies(Builder $query, string $tenantId, array $companyIds): Builder
    {
        $companyIds = array_values(array_unique(array_filter($companyIds)));
        if ($companyIds === []) {
            $query->whereRaw('1 = 0');

            return $query;
        }

        $query->whereIn('tenant_users.tenant_user_id', function ($sub) use ($tenantId, $companyIds) {
            $sub->select('tenant_user_scopes.tenant_user_id')
                ->from('tenant_user_scopes')
                ->join('tenant_scopes', 'tenant_user_scopes.scope_id', '=', 'tenant_scopes.scope_id')
                ->where('tenant_user_scopes.tenant_id', $tenantId)
                ->whereNull('tenant_user_scopes.deleted_at')
                ->whereNull('tenant_scopes.deleted_at')
                ->where('tenant_scopes.is_active', true)
                ->whereRaw('UPPER(tenant_scopes.scope_type) = ?', ['COMPANY'])
                ->where(function ($w) use ($companyIds) {
                    $w->whereIn('tenant_scopes.reference_id', $companyIds)
                        ->orWhereExists(function ($ex) use ($companyIds) {
                            $ex->selectRaw('1')
                                ->from('tenant_scope_members')
                                ->whereColumn('tenant_scope_members.scope_id', 'tenant_scopes.scope_id')
                                ->whereNull('tenant_scope_members.deleted_at')
                                ->whereIn('tenant_scope_members.reference_id', $companyIds);
                        });
                });
        });

        return $query;
    }

    protected function resolveTenantId(Builder $query): string
    {
        $fromContext = $this->currentTenantId();
        if ($fromContext) {
            return $fromContext;
        }

        return (string) ($query->getQuery()->wheres[0]['value'] ?? '');
    }

    protected function currentTenantId(): string
    {
        if (app()->bound('current_tenant_id') && app('current_tenant_id')) {
            return (string) app('current_tenant_id');
        }

        $ctx = Context::get('security_context');
        if (is_array($ctx) && !empty($ctx['tenant_id'])) {
            return (string) $ctx['tenant_id'];
        }

        return (string) (\App\Base\Context\TenantContext::getInstance()->getTenantId() ?? '');
    }

    protected function currentUserIsTenantOwner(): bool
    {
        $ctx = Context::get('security_context');
        if (is_array($ctx) && !empty($ctx['is_owner'])) {
            return true;
        }

        if (app()->bound('current_security_context')) {
            $bound = app('current_security_context');
            if (is_array($bound) && !empty($bound['is_owner'])) {
                return true;
            }
        }

        return false;
    }
}
