<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Base\Context\ScopeContext;
use App\Base\Context\TenantContext;
use App\Base\Services\HierarchyCeilingSupport;
use App\Base\Services\HoldingAccessService;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * ADR-ID-ORG-005 — hierarchical scope ceiling (subtree, no peers/parents).
 */
class HierarchyCeilingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Company $root;

    protected Company $child;

    protected Company $peer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'CEIL_01',
            'status'      => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);

        $this->root = Company::withoutGlobalScopes()->create([
            'company_id'        => (string) Str::uuid(),
            'tenant_id'         => $this->tenant->tenant_id,
            'code'              => 'ROOT',
            'name'              => 'Root Co',
            'legal_name'        => 'Root Co Legal',
            'is_active'         => true,
            'is_primary'        => true,
            'parent_company_id' => null,
        ]);

        $this->child = Company::withoutGlobalScopes()->create([
            'company_id'        => (string) Str::uuid(),
            'tenant_id'         => $this->tenant->tenant_id,
            'code'              => 'CHILD',
            'name'              => 'Child Co',
            'legal_name'        => 'Child Co Legal',
            'is_active'         => true,
            'is_primary'        => false,
            'parent_company_id' => $this->root->company_id,
        ]);

        $this->peer = Company::withoutGlobalScopes()->create([
            'company_id'        => (string) Str::uuid(),
            'tenant_id'         => $this->tenant->tenant_id,
            'code'              => 'PEER',
            'name'              => 'Peer Co',
            'legal_name'        => 'Peer Co Legal',
            'is_active'         => true,
            'is_primary'        => false,
            'parent_company_id' => null,
        ]);
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function company_subtree_includes_self_and_descendants_not_peers(): void
    {
        $support = app(HierarchyCeilingSupport::class);
        $ids = $support->companySubtreeIds($this->tenant->tenant_id, [(string) $this->root->company_id]);

        $this->assertContains((string) $this->root->company_id, $ids);
        $this->assertContains((string) $this->child->company_id, $ids);
        $this->assertNotContains((string) $this->peer->company_id, $ids);
    }

    #[Test]
    public function company_operator_ceiling_is_subtree_of_scoped_companies(): void
    {
        $user = User::factory()->create(['status' => 1]);
        $tu = TenantUser::factory()->create([
            'tenant_id'       => $this->tenant->tenant_id,
            'user_id'         => $user->user_id,
            'status'          => 1,
            'is_owner'        => false,
            'home_company_id' => $this->root->company_id,
        ]);

        $scopeRoot = TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Root scope',
            'scope_type'   => 'COMPANY',
            'reference_id' => $this->root->company_id,
            'is_active'    => true,
        ]);

        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id'  => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $tu->tenant_user_id,
            'scope_id'       => $scopeRoot->scope_id,
        ]);

        Context::add('security_context', [
            'is_owner'       => false,
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $tu->tenant_user_id,
            'user_id'        => $user->user_id,
        ]);
        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => $scopeRoot->scope_id,
                'scope_type'   => 'COMPANY',
                'reference_id' => $this->root->company_id,
                'scope_name'   => 'Root scope',
            ],
        ], $tu->tenant_user_id);

        $svc = app(HoldingAccessService::class);
        $ceiling = $svc->actorCeilingCompanyIds();

        $this->assertContains((string) $this->root->company_id, $ceiling);
        $this->assertContains((string) $this->child->company_id, $ceiling);
        $this->assertNotContains((string) $this->peer->company_id, $ceiling);
    }

    #[Test]
    public function operator_cannot_grant_peer_company_scope(): void
    {
        $user = User::factory()->create(['status' => 1]);
        $tu = TenantUser::factory()->create([
            'tenant_id'       => $this->tenant->tenant_id,
            'user_id'         => $user->user_id,
            'status'          => 1,
            'is_owner'        => false,
            'home_company_id' => $this->root->company_id,
        ]);

        $scopeRoot = TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Root scope',
            'scope_type'   => 'COMPANY',
            'reference_id' => $this->root->company_id,
            'is_active'    => true,
        ]);
        $scopePeer = TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Peer scope',
            'scope_type'   => 'COMPANY',
            'reference_id' => $this->peer->company_id,
            'is_active'    => true,
        ]);

        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id'  => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $tu->tenant_user_id,
            'scope_id'       => $scopeRoot->scope_id,
        ]);

        Context::add('security_context', [
            'is_owner'       => false,
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $tu->tenant_user_id,
            'user_id'        => $user->user_id,
        ]);
        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => $scopeRoot->scope_id,
                'scope_type'   => 'COMPANY',
                'reference_id' => $this->root->company_id,
                'scope_name'   => 'Root scope',
            ],
        ], $tu->tenant_user_id);

        $this->expectException(RuntimeException::class);
        app(HoldingAccessService::class)->assertCanGrantScopeIds([(string) $scopePeer->scope_id]);
    }

    #[Test]
    public function operator_denied_when_target_home_outside_ceiling(): void
    {
        $user = User::factory()->create(['status' => 1]);
        $tu = TenantUser::factory()->create([
            'tenant_id'       => $this->tenant->tenant_id,
            'user_id'         => $user->user_id,
            'status'          => 1,
            'is_owner'        => false,
            'home_company_id' => $this->root->company_id,
        ]);

        $targetUser = User::factory()->create(['status' => 1]);
        $target = TenantUser::factory()->create([
            'tenant_id'       => $this->tenant->tenant_id,
            'user_id'         => $targetUser->user_id,
            'status'          => 1,
            'is_owner'        => false,
            'home_company_id' => $this->peer->company_id,
        ]);

        $scopeRoot = TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Root scope',
            'scope_type'   => 'COMPANY',
            'reference_id' => $this->root->company_id,
            'is_active'    => true,
        ]);

        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id'  => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $tu->tenant_user_id,
            'scope_id'       => $scopeRoot->scope_id,
        ]);
        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id'  => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $target->tenant_user_id,
            'scope_id'       => $scopeRoot->scope_id,
        ]);

        Context::add('security_context', [
            'is_owner'       => false,
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $tu->tenant_user_id,
            'user_id'        => $user->user_id,
        ]);
        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => $scopeRoot->scope_id,
                'scope_type'   => 'COMPANY',
                'reference_id' => $this->root->company_id,
                'scope_name'   => 'Root scope',
            ],
        ], $tu->tenant_user_id);

        $this->expectException(RuntimeException::class);
        app(HoldingAccessService::class)->assertCanManageTenantUser((string) $target->tenant_user_id);
    }
}
