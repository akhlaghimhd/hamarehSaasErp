<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Base\Context\ScopeContext;
use App\Base\Context\TenantContext;
use App\Base\Services\HoldingAccessService;
use App\Base\Services\HierarchyCeilingSupport;
use App\Modules\IdentityCore\Catalog\RoleGrantTier;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Services\RoleGrantCeilingService;
use App\Modules\IdentityCore\Services\RoleService;
use App\Modules\IdentityCore\Services\UserService;
use App\Modules\Organization\Models\Company;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\SecurityMatrixReporter;
use Tests\TestCase;

/** Holding access security matrix — ADR-ID-ORG-003/005/006. */
class HoldingAccessSecurityMatrixTest extends TestCase
{
    use RefreshDatabase;
    use HoldingAccessSecurityMatrixExtra;

    protected Tenant $tenant;
    protected Company $primary;
    protected Company $childB;
    protected Company $peer;
    protected TenantScope $scopePrimary;
    protected TenantScope $scopeChild;
    protected TenantScope $scopePeer;
    protected TenantRole $rolePlatformAdmin;
    protected TenantRole $roleGroupObserver;
    protected TenantRole $roleCompanyOp;
    protected TenantRole $roleCompanyUser;

    private static bool $matrixReporterStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (! self::$matrixReporterStarted) {
            SecurityMatrixReporter::reset();
            self::$matrixReporterStarted = true;
        }

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'HSM_'.substr((string) Str::uuid(), 0, 8),
            'status'      => 1,
        ]);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);

        $this->primary = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->tenant_id,
            'code' => 'PRIMARY', 'name' => 'Primary HQ', 'legal_name' => 'Primary HQ Legal',
            'is_active' => true, 'is_primary' => true, 'parent_company_id' => null,
        ]);
        $this->childB = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->tenant_id,
            'code' => 'CHILD-B', 'name' => 'Child B', 'legal_name' => 'Child B Legal',
            'is_active' => true, 'is_primary' => false, 'parent_company_id' => $this->primary->company_id,
        ]);
        $this->peer = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->tenant_id,
            'code' => 'PEER', 'name' => 'Peer Co', 'legal_name' => 'Peer Legal',
            'is_active' => true, 'is_primary' => false, 'parent_company_id' => null,
        ]);

        $this->scopePrimary = $this->makeCompanyScope('PRIMARY', $this->primary->company_id);
        $this->scopeChild = $this->makeCompanyScope('CHILD-B', $this->childB->company_id);
        $this->scopePeer = $this->makeCompanyScope('PEER', $this->peer->company_id);
        $this->rolePlatformAdmin = $this->makeRole('platform-admin', 'مالک فنی پلتفرم');
        $this->roleGroupObserver = $this->makeRole('group-observer', 'ناظر گروه');
        $this->roleCompanyOp = $this->makeRole('company-operator', 'اپراتور شرکت');
        $this->roleCompanyUser = $this->makeRole('company-user', 'کاربر شرکت');
    }

    protected function tearDown(): void
    {
        SecurityMatrixReporter::flush('holding_access');
        ScopeContext::resetInstance();
        Context::flush();
        parent::tearDown();
    }

    private function makeCompanyScope(string $name, string $companyId): TenantScope
    {
        return TenantScope::withoutGlobalScopes()->create([
            'scope_id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->tenant_id,
            'scope_name' => $name, 'scope_type' => 'COMPANY', 'reference_id' => $companyId, 'is_active' => true,
        ]);
    }

    private function makeRole(string $code, string $name): TenantRole
    {
        return TenantRole::withoutGlobalScopes()->create([
            'tenant_role_id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->tenant_id,
            'code' => $code, 'name' => $name, 'status' => 1, 'is_system_default' => true,
        ]);
    }

    private function makeMember(bool $owner, ?string $homeCompanyId, ?TenantScope $scope): array
    {
        $user = User::factory()->create(['status' => 1]);
        $tu = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id, 'user_id' => $user->user_id,
            'status' => 1, 'is_owner' => $owner, 'home_company_id' => $homeCompanyId,
        ]);
        if ($scope) {
            TenantUserScope::withoutGlobalScopes()->create([
                'assignment_id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->tenant_id,
                'tenant_user_id' => $tu->tenant_user_id, 'scope_id' => $scope->scope_id,
            ]);
        }
        return [$user, $tu];
    }

    private function bindActor(User $user, TenantUser $tu, array $scopePayload, bool $isOwner = false): void
    {
        Context::add('security_context', [
            'is_owner' => $isOwner, 'tenant_id' => $this->tenant->tenant_id,
            'tenant_user_id' => $tu->tenant_user_id, 'user_id' => $user->user_id,
        ]);
        ScopeContext::getInstance()->setScopes($scopePayload, $tu->tenant_user_id);
        app()->forgetInstance(HoldingAccessService::class);
        app()->forgetInstance(RoleGrantCeilingService::class);
    }

    private function record(string $id, string $category, string $severity, string $title, string $expected, bool $ok, string $law = '', string $detail = ''): void
    {
        SecurityMatrixReporter::record($id, $category, $severity, $title, $expected, $ok ? 'PASS' : 'FAIL', $law, $detail);
        $this->assertTrue($ok, "[$id] $title — $detail");
    }

    private function childScopePayload(): array
    {
        return [['scope_id' => $this->scopeChild->scope_id, 'scope_type' => 'COMPANY', 'reference_id' => $this->childB->company_id, 'scope_name' => 'CHILD-B']];
    }

    private function primaryScopePayload(): array
    {
        return [['scope_id' => $this->scopePrimary->scope_id, 'scope_type' => 'COMPANY', 'reference_id' => $this->primary->company_id, 'scope_name' => 'PRIMARY']];
    }

    #[Test]
    public function hsm_c01_company_op_lists_only_same_company_members(): void
    {
        [$opUser, $opTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        [, $mA] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        [, $mB] = $this->makeMember(false, $this->peer->company_id, $this->scopePeer);
        $this->bindActor($opUser, $opTu, $this->childScopePayload());
        $list = app(UserService::class)->listTenantUsers('active');
        $ids = $list->pluck('tenant_user_id')->map(fn ($id) => (string) $id)->all();
        $ok = in_array((string) $mA->tenant_user_id, $ids, true) && ! in_array((string) $mB->tenant_user_id, $ids, true);
        $this->record('HSM-C01', 'conventional', 'high', 'لیست اعضا فقط شرکت خود', 'only same company', $ok, 'ADR-ID-ORG-003');
    }

    #[Test]
    public function hsm_c02_company_op_cannot_manage_sibling(): void
    {
        [$opUser, $opTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        [, $mPeer] = $this->makeMember(false, $this->peer->company_id, $this->scopePeer);
        $this->bindActor($opUser, $opTu, $this->childScopePayload());
        $denied = false;
        try { app(HoldingAccessService::class)->assertCanManageTenantUser((string) $mPeer->tenant_user_id); } catch (\Throwable $e) { $denied = true; }
        $this->record('HSM-C02', 'conventional', 'critical', 'عدم manage عضو sibling', 'DENY', $denied, 'ADR-ID-ORG-003');
    }

    #[Test]
    public function hsm_c03_company_tier_role_to_child_by_owner_allowed(): void
    {
        [$ownerUser, $ownerTu] = $this->makeMember(true, $this->primary->company_id, $this->scopePrimary);
        [, $targetTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        $this->bindActor($ownerUser, $ownerTu, $this->primaryScopePayload(), true);
        $ok = true; $detail = '';
        try {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds($this->tenant->tenant_id, (string) $targetTu->user_id, [(string) $this->roleCompanyOp->tenant_role_id]);
        } catch (\Throwable $e) { $ok = false; $detail = $e->getMessage(); }
        $this->record('HSM-C03', 'conventional', 'medium', 'اعطای نقش company به CHILD توسط owner', 'ALLOW', $ok, 'ID-ROLE-L04', $detail);
    }

    #[Test]
    public function hsm_x01_platform_admin_denied_for_child_scoped_target(): void
    {
        [$ownerUser, $ownerTu] = $this->makeMember(true, $this->primary->company_id, $this->scopePrimary);
        [, $childTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        $this->bindActor($ownerUser, $ownerTu, $this->primaryScopePayload(), true);
        $denied = false; $detail = '';
        try {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds($this->tenant->tenant_id, (string) $childTu->user_id, [(string) $this->rolePlatformAdmin->tenant_role_id]);
        } catch (RuntimeException $e) {
            $denied = str_contains($e->getMessage(), 'اسکوپ سطح بالاتر') || str_contains($e->getMessage(), 'سقف');
            $detail = $e->getMessage();
        }
        $this->record('HSM-X01', 'complex', 'critical', 'platform-admin به کاربر CHILD', 'DENY', $denied, 'ID-ROLE-L04/L05', $detail);
    }

    #[Test]
    public function hsm_x02_group_observer_denied_for_child_scoped_target(): void
    {
        [$ownerUser, $ownerTu] = $this->makeMember(true, $this->primary->company_id, $this->scopePrimary);
        [, $childTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        $this->bindActor($ownerUser, $ownerTu, $this->primaryScopePayload(), true);
        $denied = false; $detail = '';
        try {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds($this->tenant->tenant_id, (string) $childTu->user_id, [(string) $this->roleGroupObserver->tenant_role_id]);
        } catch (RuntimeException $e) { $denied = true; $detail = $e->getMessage(); }
        $this->record('HSM-X02', 'complex', 'critical', 'group-observer به کاربر CHILD', 'DENY', $denied, 'ID-ROLE-L04', $detail);
    }

    #[Test]
    public function hsm_x03_primary_scope_raises_target_capacity_to_group(): void
    {
        [, $tu] = $this->makeMember(false, $this->primary->company_id, $this->scopePrimary);
        $rank = app(RoleGrantCeilingService::class)->targetCapacityRank($this->tenant->tenant_id, (string) $tu->user_id);
        $ok = $rank >= RoleGrantTier::rank(RoleGrantTier::GROUP);
        $this->record('HSM-X03', 'complex', 'high', 'اسکوپ primary ⇒ ظرفیت ≥ group', 'rank>=3', $ok, 'ID-ROLE-L02', 'rank='.$rank);
    }

    #[Test]
    public function hsm_x04_company_actor_cannot_grant_group_tier(): void
    {
        [$opUser, $opTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        [, $targetTu] = $this->makeMember(false, $this->primary->company_id, $this->scopePrimary);
        $this->bindActor($opUser, $opTu, $this->childScopePayload());
        $denied = false; $detail = '';
        try {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds($this->tenant->tenant_id, (string) $targetTu->user_id, [(string) $this->roleGroupObserver->tenant_role_id]);
        } catch (RuntimeException $e) { $denied = true; $detail = $e->getMessage(); }
        $this->record('HSM-X04', 'complex', 'critical', 'بازیگر company نتواند نقش group بدهد', 'DENY', $denied, 'ID-ROLE-L03', $detail);
    }

    #[Test]
    public function hsm_x05_peer_scope_grant_denied(): void
    {
        [$opUser, $opTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        $this->bindActor($opUser, $opTu, $this->childScopePayload());
        $denied = false;
        try { app(HoldingAccessService::class)->assertCanGrantScopeIds([(string) $this->scopePeer->scope_id]); } catch (\Throwable $e) { $denied = true; }
        $this->record('HSM-X05', 'complex', 'critical', 'grant اسکوپ peer', 'DENY', $denied, 'ID-CEILING-L03');
    }

    #[Test]
    public function hsm_x07_subtree_includes_child_not_peer(): void
    {
        $ids = app(HierarchyCeilingSupport::class)->companySubtreeIds($this->tenant->tenant_id, [(string) $this->primary->company_id]);
        $ok = in_array((string) $this->childB->company_id, $ids, true) && ! in_array((string) $this->peer->company_id, $ids, true);
        $this->record('HSM-X07', 'complex', 'high', 'subtree primary شامل child نه peer', 'child in / peer out', $ok, 'ID-CEILING-L02');
    }

    #[Test]
    public function hsm_a01_multi_company_empty_scopes_not_group_wide(): void
    {
        [$user, $tu] = $this->makeMember(false, null, null);
        $this->bindActor($user, $tu, []);
        $has = app(HoldingAccessService::class);
        $ok = ! $has->isGroupWideOperator() && ! $has->isIdentityMutateGroupWide();
        $this->record('HSM-A01', 'adversarial', 'critical', 'اسکوپ خالی + چندشرکتی ≠ group-wide', 'not group-wide', $ok, 'IDORG-T02');
    }

    #[Test]
    public function hsm_a02_self_elevate_to_platform_admin_denied(): void
    {
        [$opUser, $opTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        $this->bindActor($opUser, $opTu, $this->childScopePayload());
        $denied = false; $detail = '';
        try {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds($this->tenant->tenant_id, (string) $opUser->user_id, [(string) $this->rolePlatformAdmin->tenant_role_id]);
        } catch (RuntimeException $e) { $denied = true; $detail = $e->getMessage(); }
        $this->record('HSM-A02', 'adversarial', 'critical', 'خودارتقا به platform-admin', 'DENY', $denied, 'ID-ROLE-L03/L04', $detail);
    }

    #[Test]
    public function hsm_a03_assign_role_to_sibling_user_denied(): void
    {
        [$opUser, $opTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        [, $sib] = $this->makeMember(false, $this->peer->company_id, $this->scopePeer);
        $this->bindActor($opUser, $opTu, $this->childScopePayload());
        $denied = false;
        try { app(HoldingAccessService::class)->assertCanManageUserId((string) $sib->user_id); } catch (\Throwable $e) { $denied = true; }
        $this->record('HSM-A03', 'adversarial', 'critical', 'manage sibling user', 'DENY', $denied, 'ADR-ID-ORG-003');
    }

    #[Test]
    public function hsm_a05_soft_deleted_user_role_hidden_from_list(): void
    {
        [, $tu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        TenantUserRole::withoutGlobalScopes()->create([
            'tenant_user_role_id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->tenant_id,
            'user_id' => $tu->user_id, 'tenant_role_id' => $this->roleCompanyOp->tenant_role_id,
        ]);
        DB::table('tenant_user_roles')->insert([
            'tenant_user_role_id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->tenant_id,
            'user_id' => $tu->user_id, 'tenant_role_id' => $this->rolePlatformAdmin->tenant_role_id,
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => now(),
        ]);
        $codes = app(RoleService::class)->listRolesForUser((string) $tu->user_id)->pluck('code')->all();
        $ok = in_array('company-operator', $codes, true) && ! in_array('platform-admin', $codes, true);
        $this->record('HSM-A05', 'adversarial', 'high', 'نقش soft-deleted در list نیاید', 'no ghost', $ok, 'soft-delete list filter');
    }
}
