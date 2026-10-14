<?php

namespace App\Base\Services;

use App\Base\Context\ScopeContext;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ADR-ID-ORG-003 + ADR-ID-ORG-004 (G01) — Holding Access Model & Actor Kinds.
 *
 * Actor kinds (IDORG-T01):
 * - TECHNICAL_OWNER  : tenant is_owner flag — technical/admin identity only.
 *                      NOT silent super-operator of every company (FIN-HOLD-L06).
 * - GROUP_OBSERVER   : explicit read-only group capability (Wave 1 / IDORG-T09).
 *                      Placeholder until observer permission is seeded.
 * - COMPANY_OPERATOR : actor holds one or more COMPANY scopes — write/manage own only.
 * - SOFT_SME         : no COMPANY scopes and not owner — legacy single-company path.
 *
 * isGroupWideActor() remains the Identity list/membership visibility helper
 * (owner + soft SME). Operational modules MUST use isGroupWideOperator() /
 * assertCanOperateCompany-style helpers and must NOT treat is_owner as operate-all.
 */
class HoldingAccessService
{
    public const ACTOR_TECHNICAL_OWNER = 'technical_owner';

    public const ACTOR_GROUP_OBSERVER = 'group_observer';

    public const ACTOR_COMPANY_OPERATOR = 'company_operator';

    public const ACTOR_SOFT_SME = 'soft_sme';

    public function __construct(
        protected ?ScopeContext $scopeContext = null
    ) {
        $this->scopeContext = $scopeContext ?? ScopeContext::getInstance();
    }

    /**
     * Platform/tenant technical owner (is_owner on TenantUser).
     * Admin/bootstrap identity — not automatic operate-on-all-companies.
     */
    public function isTechnicalOwner(): bool
    {
        return $this->currentUserIsTenantOwner();
    }

    /**
     * Soft SME path: no COMPANY scopes assigned.
     * Valid only as gradual single-company behaviour (IDORG-T02 hardens multi-company).
     */
    public function isSoftSmePath(): bool
    {
        if ($this->isTechnicalOwner()) {
            return false;
        }

        return empty($this->scopeContext->getScopesByType('COMPANY'));
    }

    /**
     * Actor holds explicit COMPANY scope(s) — delegated company operator.
     */
    public function isCompanyOperator(): bool
    {
        return !$this->isTechnicalOwner()
            && !empty($this->scopeContext->getScopesByType('COMPANY'));
    }

    /**
     * Future group-observer role/permission (IDORG-T05/T09).
     * Currently always false until observer capability is seeded.
     */
    public function isGroupObserver(): bool
    {
        // Wave 1 will detect explicit group-read permission / role template.
        return false;
    }

    /**
     * Resolve primary actor kind for diagnostics and policy branches.
     */
    public function resolveActorKind(): string
    {
        if ($this->isTechnicalOwner()) {
            return self::ACTOR_TECHNICAL_OWNER;
        }

        if ($this->isGroupObserver()) {
            return self::ACTOR_GROUP_OBSERVER;
        }

        if ($this->isCompanyOperator()) {
            return self::ACTOR_COMPANY_OPERATOR;
        }

        return self::ACTOR_SOFT_SME;
    }

    /**
     * Identity membership list/visibility across the tenant.
     *
     * Technical owner may still see all members (tenant admin surface).
     * Soft SME path keeps current single-company behaviour.
     * Company operators are constrained to their COMPANY scopes.
     *
     * This is NOT an operational write grant on child company ledgers.
     */
    public function isGroupWideActor(): bool
    {
        if ($this->isTechnicalOwner()) {
            return true;
        }

        if ($this->isGroupObserver()) {
            return true;
        }

        return $this->isSoftSmePath();
    }

    /**
     * Operational group-wide operate capability.
     *
     * IDORG-T01 / FIN-HOLD-L03/L06: is_owner is NOT a group-wide operator.
     * Soft SME path remains operate-capable on the implied single-company path.
     * Explicit company operators never pass this check (they use actorCompanyIds).
     */
    public function isGroupWideOperator(): bool
    {
        // Technical owner must obtain explicit operate grants (or soft SME path only).
        if ($this->isTechnicalOwner()) {
            return false;
        }

        // Soft SME: no COMPANY scopes → gradual single-company operate path.
        return $this->isSoftSmePath();
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
