<?php

namespace App\Base\Services;

use App\Base\Context\ScopeContext;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ADR-ID-ORG-003 + ADR-ID-ORG-004 (G01/G02/G03) — Holding Access Model & Actor Kinds.
 *
 * Actor kinds (IDORG-T01):
 * - TECHNICAL_OWNER  : tenant is_owner flag — technical/admin identity only.
 *                      NOT silent super-operator of every company (FIN-HOLD-L06).
 * - GROUP_OBSERVER   : explicit read-only group capability (Wave 1 / IDORG-T09).
 *                      Placeholder until observer permission is seeded.
 * - COMPANY_OPERATOR : actor holds one or more COMPANY scopes — write/manage own only.
 * - SOFT_SME         : no COMPANY scopes, not owner, and tenant has ≤1 active company.
 *
 * IDORG-T02: empty COMPANY scopes on a multi-company tenant is NOT group-wide.
 * Soft SME path is only for single-company (or zero-company bootstrap) tenants.
 *
 * IDORG-T03 / FIN-HOLD-L03 — Identity list/read ≠ mutate:
 * - List/visibility  → isGroupWideActor()  (owner + soft SME + future observer)
 * - Mutate members   → isIdentityMutateGroupWide() (owner + soft SME only)
 *   Group observer must NEVER pass assertCanManage*; company operators use actorCompanyIds.
 *
 * Operational modules (Finance, …) MUST use isGroupWideOperator() /
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
     * Active companies in current tenant (non-deleted).
     * ≤1 ⇒ single-company / SME-eligible; >1 ⇒ multi-company holding.
     */
    public function isTenantSingleCompany(): bool
    {
        return $this->tenantActiveCompanyCount() <= 1;
    }

    /**
     * Soft SME path: no COMPANY scopes, not owner, and tenant is single-company.
     *
     * IDORG-T02: on multi-company tenants, empty COMPANY scopes never open group-wide access.
     */
    public function isSoftSmePath(): bool
    {
        if ($this->isTechnicalOwner()) {
            return false;
        }

        if (!empty($this->scopeContext->getScopesByType('COMPANY'))) {
            return false;
        }

        return $this->isTenantSingleCompany();
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
     *
     * Actors with no COMPANY scopes on a multi-company tenant fall through to
     * COMPANY_OPERATOR semantics with empty allowed set (no group power).
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

        if ($this->isSoftSmePath()) {
            return self::ACTOR_SOFT_SME;
        }

        // Multi-company + no COMPANY scopes: constrained empty operator (not soft SME).
        return self::ACTOR_COMPANY_OPERATOR;
    }

    /**
     * Identity membership list/visibility across the tenant (READ).
     *
     * Technical owner may still see all members (tenant admin surface).
     * Soft SME path keeps single-company behaviour.
     * Future group observer may list (read-only).
     * Company operators (and multi-company empty-scope actors) are constrained.
     *
     * This is NOT a mutate grant and NOT an operational write on child ledgers.
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
     * Identity membership mutate group-wide (WRITE on members).
     *
     * IDORG-T03 / FIN-HOLD-L03: group read ≠ group mutate.
     * - technical_owner: allowed (tenant Identity admin pack — not FIN operate-all).
     * - soft_sme: allowed on single-company path.
     * - group_observer: NEVER (read-only).
     * - company_operator: never group-wide; uses actorCompanyIds match.
     */
    public function isIdentityMutateGroupWide(): bool
    {
        if ($this->isTechnicalOwner()) {
            return true;
        }

        return $this->isSoftSmePath();
    }

    /**
     * Operational group-wide operate capability (Finance / ops modules).
     *
     * IDORG-T01 / FIN-HOLD-L03/L06: is_owner is NOT a group-wide operator.
     * Soft SME path remains operate-capable on the implied single-company path.
     * Explicit company operators never pass this check (they use actorCompanyIds).
     */
    public function isGroupWideOperator(): bool
    {
        if ($this->isTechnicalOwner()) {
            return false;
        }

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
     * Apply delegated-admin filter to a TenantUser query (LIST / READ).
     *
     * Uses isGroupWideActor() so owner + soft SME + future observer can list.
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

    /**
     * Mutate guard for a tenant membership (update / soft-delete / role assign target).
     *
     * IDORG-T03: uses isIdentityMutateGroupWide(), NOT isGroupWideActor().
     * Group observer can list but cannot manage.
     */
    public function assertCanManageTenantUser(string $tenantUserId): void
    {
        if ($this->isIdentityMutateGroupWide()) {
            return;
        }

        $companyIds = $this->actorCompanyIds();
        if ($companyIds === []) {
            // IDORG-T02: multi-company actor without COMPANY scope has no manage power.
            // IDORG-T03: pure group observer (when enabled) also has no manage power.
            throw new RuntimeException('دسترسی به این عضو در محدوده شرکت شما مجاز نیست.');
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
     * Resolve membership by platform user_id then apply company-scope manage guard.
     * Used by role assign (AssignRoleToUserDTO carries user_id, not tenant_user_id).
     */
    public function assertCanManageUserId(string $userId): void
    {
        if ($this->isIdentityMutateGroupWide()) {
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
     * Count non-deleted companies for the current tenant.
     * Overridable in unit tests. Returns 0 when tenant context is missing
     * (treat as SME-eligible bootstrap / pure unit without DB).
     */
    protected function tenantActiveCompanyCount(): int
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId === '') {
            return 0;
        }

        try {
            return (int) DB::table('erp_companies')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->count();
        } catch (\Throwable) {
            return 0;
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
