<?php

namespace App\Base\Services;

use App\Base\Context\ScopeContext;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ADR-ID-ORG-003 + ADR-ID-ORG-004 (G01–G05/G15–G17 Wave 1) — Holding Access Model & Actor Kinds.
 *
 * Actor kinds (IDORG-T01):
 * - TECHNICAL_OWNER  : tenant is_owner flag — technical/admin identity only.
 *                      NOT silent super-operator of every company (FIN-HOLD-L06).
 * - GROUP_OBSERVER   : explicit read-only group capability (Wave 1 / IDORG-T09).
 *                      Detected via identity.group.observe or role group-observer (T09).
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
 * IDORG-T04 / G04 — Role catalog mutate:
 * - create/update/soft-delete role + assignPermissionsToRole → assertCanManageRoleCatalog()
 * - Allowed: technical_owner + soft_sme only. Company operators assign existing roles to users
 *   in scope but cannot inflate the tenant-wide catalog.
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
        // Keep explicit injection for tests; otherwise resolve live singleton each call
        // so ScopeContext::resetInstance() cannot leave a stale empty context (FIN-HOLD-W1).
    }

    protected function scopeContext(): ScopeContext
    {
        return $this->scopeContext ?? ScopeContext::getInstance();
    }

    public function isTechnicalOwner(): bool
    {
        return $this->currentUserIsTenantOwner();
    }

    public function isTenantSingleCompany(): bool
    {
        return $this->tenantActiveCompanyCount() <= 1;
    }

    public function isSoftSmePath(): bool
    {
        if ($this->isTechnicalOwner()) {
            return false;
        }

        if (!empty($this->scopeContext()->getScopesByType('COMPANY'))) {
            return false;
        }

        return $this->isTenantSingleCompany();
    }

    public function isCompanyOperator(): bool
    {
        return !$this->isTechnicalOwner()
            && !empty($this->scopeContext()->getScopesByType('COMPANY'));
    }

    /**
     * Group observer: explicit identity.group.observe permission or group-observer role (IDORG-T09).
     * Read-only group visibility — never mutate members or role catalog.
     */
    public function isGroupObserver(): bool
    {
        if ($this->isTechnicalOwner()) {
            return false;
        }

        $ctx = Context::get('security_context');
        if (!is_array($ctx)) {
            if (app()->bound('current_security_context')) {
                $bound = app('current_security_context');
                $ctx = is_array($bound) ? $bound : [];
            } else {
                $ctx = [];
            }
        }

        $permissions = $ctx['permissions'] ?? [];
        if (is_array($permissions)) {
            foreach ($permissions as $p) {
                $code = is_string($p) ? $p : (string) ($p['code'] ?? '');
                if ($code === 'identity.group.observe') {
                    return true;
                }
            }
        }

        $roles = $ctx['roles'] ?? [];
        if (is_array($roles)) {
            foreach ($roles as $r) {
                $code = is_array($r) ? (string) ($r['code'] ?? '') : (string) $r;
                if ($code === 'group-observer') {
                    return true;
                }
            }
        }

        return false;
    }

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

        return self::ACTOR_COMPANY_OPERATOR;
    }

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

    public function isIdentityMutateGroupWide(): bool
    {
        if ($this->isTechnicalOwner()) {
            return true;
        }

        return $this->isSoftSmePath();
    }

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
            $this->scopeContext()->getReferenceIdsByType('COMPANY')
        )));
    }

    /**
     * @return list<string>
     */
    public function actorCompanyScopeIds(): array
    {
        $ids = [];
        foreach ($this->scopeContext()->getScopesByType('COMPANY') as $scope) {
            if (!empty($scope['scope_id'])) {
                $ids[] = (string) $scope['scope_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
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
        if ($this->isIdentityMutateGroupWide()) {
            return;
        }

        $companyIds = $this->actorCompanyIds();
        if ($companyIds === []) {
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
     * Role catalog mutate: create/update/soft-delete role, assign permissions to role.
     *
     * IDORG-T04 / G04: default deny for child company operators.
     * Allowed: technical_owner (tenant security admin pack) and soft_sme path.
     * Company operators may still assign existing roles to users in their company
     * via assertCanManageUserId — they must not invent or inflate the tenant catalog.
     * Group observer is read-only (denied).
     */
    public function assertCanManageRoleCatalog(): void
    {
        if ($this->isIdentityMutateGroupWide()) {
            return;
        }

        throw new RuntimeException(
            'مدیریت کاتالوگ نقش و تخصیص مجوز به نقش فقط برای مالک فنی سازمان یا مسیر تک‌شرکتی مجاز است.'
        );
    }

    public function canManageRoleCatalog(): bool
    {
        return $this->isIdentityMutateGroupWide();
    }

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
