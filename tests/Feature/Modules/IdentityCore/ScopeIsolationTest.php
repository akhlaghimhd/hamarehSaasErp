<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Verifies ScopeScoped trait enforces Resource-level filtering (Law 4.2 / 4.3).
 * Covers Policy B (gradual, default) and Policy A (strict) — F2.
 */
class ScopeIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected TenantUser $tenantUser;
    protected Company $company;
    protected Branch $branchAllowed;
    protected Branch $branchDenied;

    protected function setUp(): void
    {
        parent::setUp();

        // Production interim default: Policy B (gradual)
        config(['scope.enforcement_mode' => 'gradual']);

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'SCOPE_ISO',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create(['status' => 1]);

        $this->tenantUser = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);

        $this->company = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'C01',
            'name'       => 'Test Company',
            'is_active'  => true,
        ]);

        $this->branchAllowed = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->company->company_id,
            'code'       => 'BR-ALLOWED',
            'name'       => 'Allowed Branch',
            'is_active'  => true,
        ]);

        $this->branchDenied = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->company->company_id,
            'code'       => 'BR-DENIED',
            'name'       => 'Denied Branch',
            'is_active'  => true,
        ]);

        $scope = TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Allowed Branch Scope',
            'scope_type'   => 'BRANCH',
            'reference_id' => $this->branchAllowed->branch_id,
            'is_active'    => true,
        ]);

        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id'  => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $this->tenantUser->tenant_user_id,
            'scope_id'       => $scope->scope_id,
        ]);

        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => $scope->scope_id,
                'scope_name'   => 'Allowed Branch Scope',
                'scope_type'   => 'BRANCH',
                'reference_id' => $this->branchAllowed->branch_id,
            ],
        ], $this->tenantUser->tenant_user_id);
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    /** @test */
    public function branch_query_is_filtered_by_user_branch_scopes(): void
    {
        $results = Branch::query()->get();

        $ids = $results->pluck('branch_id')->toArray();

        $this->assertContains($this->branchAllowed->branch_id, $ids);
        $this->assertNotContains($this->branchDenied->branch_id, $ids);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function without_scope_isolation_returns_all_tenant_branches(): void
    {
        $results = Branch::withoutScopeIsolation()->get();

        $ids = $results->pluck('branch_id')->toArray();

        $this->assertContains($this->branchAllowed->branch_id, $ids);
        $this->assertContains($this->branchDenied->branch_id, $ids);
        $this->assertCount(2, $results);
    }

    /**
     * Policy B (gradual): no scopes of BRANCH type → no extra filter.
     *
     * @test
     */
    public function gradual_mode_when_user_has_no_branch_scopes_no_extra_filter_is_applied(): void
    {
        config(['scope.enforcement_mode' => 'gradual']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([], $this->tenantUser->tenant_user_id);

        $results = Branch::query()->get();

        $this->assertCount(2, $results);
    }

    /**
     * Policy B: user has only WAREHOUSE scopes → BRANCH type is null → no BRANCH filter.
     *
     * @test
     */
    public function gradual_mode_other_scope_type_without_branch_does_not_filter_branches(): void
    {
        config(['scope.enforcement_mode' => 'gradual']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => (string) Str::uuid(),
                'scope_name'   => 'Warehouse Only',
                'scope_type'   => 'WAREHOUSE',
                'reference_id' => (string) Str::uuid(),
            ],
        ], $this->tenantUser->tenant_user_id);

        $results = Branch::query()->get();

        $this->assertCount(2, $results);
    }

    /**
     * Scopes of BRANCH type exist but reference_id list is empty → zero rows (both policies).
     *
     * @test
     */
    public function empty_reference_ids_yield_zero_rows(): void
    {
        config(['scope.enforcement_mode' => 'gradual']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => (string) Str::uuid(),
                'scope_name'   => 'Branch Scope Without Reference',
                'scope_type'   => 'BRANCH',
                'reference_id' => null,
            ],
        ], $this->tenantUser->tenant_user_id);

        $results = Branch::query()->get();

        $this->assertCount(0, $results);
    }

    /**
     * Policy A (strict): no BRANCH scopes → zero rows.
     *
     * @test
     */
    public function strict_mode_when_user_has_no_branch_scopes_returns_zero_rows(): void
    {
        config(['scope.enforcement_mode' => 'strict']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([], $this->tenantUser->tenant_user_id);

        $results = Branch::query()->get();

        $this->assertCount(0, $results);
    }

    /**
     * Policy A: other scope type without BRANCH still denies branches (fail-closed).
     *
     * @test
     */
    public function strict_mode_other_scope_type_without_branch_returns_zero_rows(): void
    {
        config(['scope.enforcement_mode' => 'strict']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => (string) Str::uuid(),
                'scope_name'   => 'Warehouse Only',
                'scope_type'   => 'WAREHOUSE',
                'reference_id' => (string) Str::uuid(),
            ],
        ], $this->tenantUser->tenant_user_id);

        $results = Branch::query()->get();

        $this->assertCount(0, $results);
    }

    /**
     * Policy A with valid BRANCH scopes still filters to allowed reference only.
     *
     * @test
     */
    public function strict_mode_still_filters_to_allowed_reference_when_scopes_present(): void
    {
        config(['scope.enforcement_mode' => 'strict']);

        $results = Branch::query()->get();

        $ids = $results->pluck('branch_id')->toArray();

        $this->assertContains($this->branchAllowed->branch_id, $ids);
        $this->assertNotContains($this->branchDenied->branch_id, $ids);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function current_user_has_access_to_helper_works_in_gradual_mode(): void
    {
        config(['scope.enforcement_mode' => 'gradual']);

        $this->assertTrue(Branch::currentUserHasAccessTo($this->branchAllowed->branch_id));
        $this->assertFalse(Branch::currentUserHasAccessTo($this->branchDenied->branch_id));
    }

    /** @test */
    public function current_user_has_access_to_helper_denies_all_when_strict_and_no_scopes(): void
    {
        config(['scope.enforcement_mode' => 'strict']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([], $this->tenantUser->tenant_user_id);

        $this->assertFalse(Branch::currentUserHasAccessTo($this->branchAllowed->branch_id));
        $this->assertFalse(Branch::currentUserHasAccessTo($this->branchDenied->branch_id));
    }
}
