<?php

namespace App\Base\Services;

use App\Base\Context\ScopeContext;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ADR-ID-ORG-003 + ADR-ID-ORG-004 — Holding Access Model & Actor Kinds.
 *
 * Actor kinds (IDORG-T01):
 * - TECHNICAL_OWNER  : tenant is_owner — technical/admin identity only (not operate-all).
 * - GROUP_OBSERVER   : identity.group.observe / group-observer role — read-only group.
 * - COMPANY_OPERATOR : one or more COMPANY scopes — write/manage own only.
 * - SOFT_SME         : no COMPANY scopes, not owner, tenant has <=1 active company.
 *
 * IDORG-T02: empty COMPANY scopes on multi-company tenant is NOT group-wide.
 * IDORG-T03: list via isGroupWideActor; mutate via isIdentityMutateGroupWide.
 * IDORG-T04: role catalog mutate via assertCanManageRoleCatalog (owner + soft SME).
 * IDORG-T15/T16: BRANCH scopes participate in member list/manage and scope grant.
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

    /** IDORG-T15 / G09 */
    public function actorBranchIds(): array
    {
        return array_values(array_unique(array_filter(
            $this->scopeContext()->getReferenceIdsByType('BRANCH')
        )));
    }

    public function isBranchOperator(): bool
    {
        return !$this->isTechnicalOwner()
            && !empty($this->scopeContext()->getScopesByType('BRANCH'));
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
        $branchIds = $this->actorBranchIds();

        if ($companyIds === [] && $branchIds === []) {
            $query->whereRaw('1 = 0');

            return $query;
        }

        if ($branchIds === []) {
            return $this->whereTenantUserInCompanies($query, $tenantId, $companyIds);
        }

        if ($companyIds === []) {
            return $this->whereTenantUserInBranches($query, $tenantId, $branchIds);
        }

        $query->where(function ($q) use ($tenantId, $companyIds, $branchIds) {
            $q->where(function ($q2) use ($tenantId, $companyIds) {
                $this->whereTenantUserInCompanies($q2, $tenantId, $companyIds);
            })->orWhere(function ($q3) use ($tenantId, $branchIds) {
                $this->whereTenantUserInBranches($q3, $tenantId, $branchIds);
            });
        });

        return $query;
    }

    public function assertCanManageTenantUser(string $tenantUserId): void
    {
        if ($this->isIdentityMutateGroupWide()) {
            return;
        }

        $companyIds = $this->actorCompanyIds();
        $branchIds = $this->actorBranchIds();
        if ($companyIds === [] && $branchIds === []) {
            throw new RuntimeException('دسترسی به این عضو در محدوده شرکت یا شعبه شما مجاز نیست.');
        }

        $tenantId = $this->currentTenantId();
        $exists = TenantUser::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($tenantId, $companyIds, $branchIds) {
                if ($companyIds !== []) {
                    $q->where(function ($q2) use ($tenantId, $companyIds) {
                        $this->whereTenantUserInCompanies($q2, $tenantId, $companyIds);
                    });
                }
                if ($branchIds !== []) {
                    $method = $companyIds !== [] ? 'orWhere' : 'where';
                    $q->{$method}(function ($q3) use ($tenantId, $branchIds) {
                        $this->whereTenantUserInBranches($q3, $tenantId, $branchIds);
                    });
                }
            })
            ->exists();

        if (!$exists) {
            throw new RuntimeException('دسترسی به این عضو در محدوده شرکت یا شعبه شما مجاز نیست.');
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
            throw new RuntimeException('کاربر عضویت یافت نشد.');
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

    public function assertCanManageRoleCatalog(): void
    {
        if ($this->isIdentityMutateGroupWide()) {
            return;
        }

        throw new RuntimeException(
            'مدیریت کاتالوگ نقش و تخصیص مجوز نقش فقط برای مالک فنی یا مسیر تک‌شرکتی مجاز است.'
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

    /** IDORG-T15 — members with BRANCH scope in $branchIds */
    protected function whereTenantUserInBranches(Builder $query, string $tenantId, array $branchIds): Builder
    {
        $branchIds = array_values(array_unique(array_filter($branchIds)));
        if ($branchIds === []) {
            $query->whereRaw('1 = 0');

            return $query;
        }

        $query->whereIn('tenant_users.tenant_user_id', function ($sub) use ($tenantId, $branchIds) {
            $sub->select('tenant_user_scopes.tenant_user_id')
                ->from('tenant_user_scopes')
                ->join('tenant_scopes', 'tenant_user_scopes.scope_id', '=', 'tenant_scopes.scope_id')
                ->where('tenant_user_scopes.tenant_id', $tenantId)
                ->whereNull('tenant_user_scopes.deleted_at')
                ->whereNull('tenant_scopes.deleted_at')
                ->where('tenant_scopes.is_active', true)
                ->whereRaw('UPPER(tenant_scopes.scope_type) = ?', ['BRANCH'])
                ->where(function ($w) use ($branchIds) {
                    $w->whereIn('tenant_scopes.reference_id', $branchIds)
                        ->orWhereExists(function ($ex) use ($branchIds) {
                            $ex->selectRaw('1')
                                ->from('tenant_scope_members')
                                ->whereColumn('tenant_scope_members.scope_id', 'tenant_scopes.scope_id')
                                ->whereIn('tenant_scope_members.reference_id', $branchIds);
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

    /**
     * IDORG-T13 / T16 — grant COMPANY within actor companies; BRANCH within actor branches.
     *
     * @param  list<string>  $scopeIds
     */
    public function assertCanGrantScopeIds(array $scopeIds): void
    {
        if ($this->isIdentityMutateGroupWide()) {
            return;
        }

        $scopeIds = array_values(array_unique(array_filter(array_map('strval', $scopeIds))));
        if ($scopeIds === []) {
            return;
        }

        $tenantId = $this->currentTenantId();
        $allowedCompanies = $this->actorCeilingCompanyIds();
        $allowedBranches = $this->actorCeilingBranchIds();
        if ($allowedCompanies === [] && $allowedBranches === []) {
            throw new RuntimeException('اعطای محدوده خارج از شرکت‌ها/شعب مجاز شما امکان‌پذیر نیست.');
        }

        $scopes = DB::table('tenant_scopes')
            ->where('tenant_id', $tenantId)
            ->whereIn('scope_id', $scopeIds)
            ->whereNull('deleted_at')
            ->get(['scope_id', 'scope_type', 'reference_id']);

        $found = [];
        foreach ($scopes as $row) {
            $found[] = (string) $row->scope_id;
            $type = strtoupper((string) $row->scope_type);

            if ($type === 'BRANCH') {
                $ref = (string) ($row->reference_id ?? '');
                $refs = $ref !== '' ? [$ref] : [];
                try {
                    $memberRefs = DB::table('tenant_scope_members')
                        ->where('scope_id', $row->scope_id)
                        ->whereNull('deleted_at')
                        ->pluck('reference_id')
                        ->map(fn ($id) => (string) $id)
                        ->all();
                    $refs = array_values(array_unique(array_filter(array_merge($refs, $memberRefs))));
                } catch (\Throwable) {
                }
                if ($refs === [] || $allowedBranches === []) {
                    throw new RuntimeException('اعطای محدوده شعبه خارج از شعب مجاز شما امکان‌پذیر نیست.');
                }
                foreach ($refs as $rid) {
                    if (! in_array($rid, $allowedBranches, true)) {
                        throw new RuntimeException('اعطای محدوده شعبه خارج از شعب مجاز شما امکان‌پذیر نیست.');
                    }
                }
                continue;
            }

            if ($type !== 'COMPANY') {
                throw new RuntimeException('اعطای این نوع محدوده فقط برای مالک فنی یا مسیر تک‌شرکتی مجاز است.');
            }
            if ($allowedCompanies === []) {
                throw new RuntimeException('اعطای محدوده خارج از شرکت‌های مجاز شما امکان‌پذیر نیست.');
            }
            $ref = (string) ($row->reference_id ?? '');
            $refs = $ref !== '' ? [$ref] : [];
            try {
                $memberRefs = DB::table('tenant_scope_members')
                    ->where('scope_id', $row->scope_id)
                    ->whereNull('deleted_at')
                    ->pluck('reference_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();
                $refs = array_values(array_unique(array_filter(array_merge($refs, $memberRefs))));
            } catch (\Throwable) {
            }
            if ($refs === []) {
                throw new RuntimeException('محدوده بدون مرجع شرکت قابل اعطا توسط اپراتور شرکت نیست.');
            }
            foreach ($refs as $rid) {
                if (! in_array($rid, $allowedCompanies, true)) {
                    throw new RuntimeException('اعطای محدوده خارج از شرکت‌های مجاز شما امکان‌پذیر نیست.');
                }
            }
        }

        if (array_diff($scopeIds, $found) !== []) {
            throw new RuntimeException('یک یا چند محدوده یافت نشد.');
        }
    }

    public function actorCeilingCompanyIds(): array
    {
        $support = app(\App\Base\Services\HierarchyCeilingSupport::class);
        if ($this->isIdentityMutateGroupWide()) {
            return $support->allActiveCompanyIds($this->currentTenantId());
        }
        $roots = $this->actorCompanyIds();
        if ($roots === []) {
            $branchIds = $this->actorBranchIds();
            if ($branchIds === []) {
                return [];
            }
            $roots = $support->companyIdsForBranches($this->currentTenantId(), $branchIds);
        }
        return $support->companySubtreeIds($this->currentTenantId(), $roots);
    }

    public function actorCeilingBranchIds(): array
    {
        $support = app(\App\Base\Services\HierarchyCeilingSupport::class);
        if ($this->isIdentityMutateGroupWide()) {
            return $support->allActiveBranchIds($this->currentTenantId());
        }
        return $support->branchesInCeiling(
            $this->currentTenantId(),
            $this->actorCeilingCompanyIds(),
            $this->actorBranchIds()
        );
    }

    public function assertTargetHomeWithinCeiling(string $tenantUserId): void
    {
        if ($this->isIdentityMutateGroupWide()) {
            return;
        }
        $tenantId = $this->currentTenantId();
        $home = \Illuminate\Support\Facades\DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->whereNull('deleted_at')
            ->value('home_company_id');
        if ($home === null || $home === '') {
            return;
        }
        $ceiling = $this->actorCeilingCompanyIds();
        if ($ceiling === [] || !in_array((string) $home, $ceiling, true)) {
            throw new \RuntimeException('مدیریت این عضو خارج از سقف سلسله‌مراتب شرکت شما مجاز نیست.');
        }
    }
}