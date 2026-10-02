<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Base\Context\ScopeContext;
use App\Base\Context\TenantContext;
use App\Base\Services\HoldingAccessService;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Services\UserService;
use App\Modules\Organization\Models\Company;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADR-ID-ORG-003 H1/H2: delegated admin member list isolation.
 */
class HoldingDelegatedAdminTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Company $companyA;
    protected Company $companyB;
    protected TenantUser $adminA;
    protected TenantUser $memberA;
    protected TenantUser $memberB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'HOLD_DEL',
            'status'      => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);

        $this->companyA = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'CO-A',
            'name'       => 'Company A',
            'is_active'  => true,
        ]);
        $this->companyB = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'CO-B',
            'name'       => 'Company B',
            'is_active'  => true,
        ]);

        $userAdminA = User::factory()->create(['status' => 1]);
        $userA = User::factory()->create(['status' => 1]);
        $userB = User::factory()->create(['status' => 1]);

        $this->adminA = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $userAdminA->user_id,
            'status'    => 1,
            'is_owner'  => false,
        ]);
        $this->memberA = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $userA->user_id,
            'status'    => 1,
            'is_owner'  => false,
        ]);
        $this->memberB = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $userB->user_id,
            'status'    => 1,
            'is_owner'  => false,
        ]);

        $scopeA = TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Co A',
            'scope_type'   => 'COMPANY',
            'reference_id' => $this->companyA->company_id,
            'is_active'    => true,
        ]);
        $scopeB = TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Co B',
            'scope_type'   => 'COMPANY',
            'reference_id' => $this->companyB->company_id,
            'is_active'    => true,
        ]);

        foreach ([$this->adminA, $this->memberA] as $tu) {
            TenantUserScope::withoutGlobalScopes()->create([
                'assignment_id'  => (string) Str::uuid(),
                'tenant_id'      => $this->tenant->tenant_id,
                'tenant_user_id' => $tu->tenant_user_id,
                'scope_id'       => $scopeA->scope_id,
            ]);
        }
        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id'  => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $this->memberB->tenant_user_id,
            'scope_id'       => $scopeB->scope_id,
        ]);

        // Act as company-A admin (not owner)
        Context::add('security_context', [
            'is_owner'       => false,
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $this->adminA->tenant_user_id,
            'user_id'        => $userAdminA->user_id,
        ]);
        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => $scopeA->scope_id,
                'scope_type'   => 'COMPANY',
                'reference_id' => $this->companyA->company_id,
                'scope_name'   => 'Co A',
            ],
        ], $this->adminA->tenant_user_id);
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function delegated_admin_lists_only_same_company_members(): void
    {
        $list = app(UserService::class)->listTenantUsers('active');
        $ids = $list->pluck('tenant_user_id')->map(fn ($id) => (string) $id)->all();

        $this->assertContains((string) $this->adminA->tenant_user_id, $ids);
        $this->assertContains((string) $this->memberA->tenant_user_id, $ids);
        $this->assertNotContains((string) $this->memberB->tenant_user_id, $ids);
    }

    #[Test]
    public function delegated_admin_cannot_show_sibling_member(): void
    {
        // Global holding scope on TenantUser → firstOrFail → ModelNotFoundException
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(UserService::class)->getTenantUser((string) $this->memberB->tenant_user_id);
    }

    #[Test]
    public function group_wide_actor_sees_all_when_no_company_scopes(): void
    {
        Context::add('security_context', [
            'is_owner'  => false,
            'tenant_id' => $this->tenant->tenant_id,
        ]);
        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([]);

        $svc = app(HoldingAccessService::class);
        $this->assertTrue($svc->isGroupWideActor());

        $list = app(UserService::class)->listTenantUsers('active');
        $this->assertGreaterThanOrEqual(3, $list->count());
    }
}
