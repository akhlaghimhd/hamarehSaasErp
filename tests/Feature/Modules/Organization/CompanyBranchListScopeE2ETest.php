<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * Phase A – E2E Scope on list Company / Branch APIs.
 *
 * Flow:
 *  - Seed two companies and three branches under one tenant
 *  - Assign COMPANY scope (company A) + BRANCH scope (one branch of company A)
 *  - Login as scoped user
 *  - GET /api/organization/companies → only company A
 *  - GET /api/organization/companies/{companyA}/branches → only allowed branch
 *  - Out-of-scope company/branch access is fail-closed by ScopeAccessGuard (403)
 */
class CompanyBranchListScopeE2ETest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $scopedUser;
    protected TenantUser $scopedTenantUser;
    protected string $plainPassword = 'SecurePassword123!';
    protected string $scopedToken;

    protected Company $companyAllowed;
    protected Company $companyDenied;
    protected Branch $branchAllowed;
    protected Branch $branchSiblingDenied;
    protected Branch $branchOtherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scope.enforcement_mode' => 'gradual']);

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'ORG_LIST_SCOPE',
            'status'      => 1,
        ]);

        $this->scopedUser = User::factory()->create([
            'status' => 1,
            'email'  => 'org.list.scope@example.com',
        ]);

        UserCredential::create([
            'credential_id'       => (string) Str::uuid(),
            'user_id'             => $this->scopedUser->user_id,
            'password_hash'       => Hash::make($this->plainPassword),
            'authentication_type' => 1,
            'is_verified'         => true,
            'two_factor_enabled'  => false,
            'failed_login_count'  => 0,
        ]);

        $this->scopedTenantUser = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->scopedUser->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'org-list-viewer',
            'status'    => 1,
        ]);

        foreach ([
            'organization.company.view',
            'organization.branch.view',
        ] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Organization',
                'status'               => 1,
            ]);

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenant->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->scopedUser->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->companyAllowed = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'CO-ALLOW',
            'name'       => 'Allowed Company',
            'is_active'  => true,
        ]);

        $this->companyDenied = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'CO-DENY',
            'name'       => 'Denied Company',
            'is_active'  => true,
        ]);

        $this->branchAllowed = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->companyAllowed->company_id,
            'code'       => 'BR-ALLOW',
            'name'       => 'Allowed Branch',
            'is_active'  => true,
        ]);

        $this->branchSiblingDenied = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->companyAllowed->company_id,
            'code'       => 'BR-SIBLING',
            'name'       => 'Sibling Denied Branch',
            'is_active'  => true,
        ]);

        $this->branchOtherCompany = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->companyDenied->company_id,
            'code'       => 'BR-OTHER',
            'name'       => 'Other Company Branch',
            'is_active'  => true,
        ]);

        $companyScope = TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Company A Scope',
            'scope_type'   => 'COMPANY',
            'reference_id' => $this->companyAllowed->company_id,
            'is_active'    => true,
        ]);

        $branchScope = TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Branch Allowed Scope',
            'scope_type'   => 'BRANCH',
            'reference_id' => $this->branchAllowed->branch_id,
            'is_active'    => true,
        ]);

        foreach ([$companyScope, $branchScope] as $scope) {
            TenantUserScope::withoutGlobalScopes()->create([
                'assignment_id'  => (string) Str::uuid(),
                'tenant_id'      => $this->tenant->tenant_id,
                'tenant_user_id' => $this->scopedTenantUser->tenant_user_id,
                'scope_id'       => $scope->scope_id,
            ]);
        }

        $login = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/login', [
            'email'     => 'org.list.scope@example.com',
            'password'  => $this->plainPassword,
            'tenant_id' => $this->tenant->tenant_id,
        ]);

        $login->assertStatus(200);
        $this->scopedToken = $login->json('data.access_token');
        $this->assertNotEmpty($this->scopedToken);

        $scopes = $login->json('data.security_context.scopes');
        $this->assertCount(2, $scopes);
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->scopedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function company_list_returns_only_scoped_company(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/organization/companies');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $ids = collect($response->json('data'))->pluck('company_id')->toArray();

        $this->assertContains($this->companyAllowed->company_id, $ids);
        $this->assertNotContains($this->companyDenied->company_id, $ids);
        $this->assertCount(1, $ids);
    }

    #[Test]
    public function branch_list_under_allowed_company_returns_only_scoped_branch(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/organization/companies/' . $this->companyAllowed->company_id . '/branches');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $ids = collect($response->json('data'))->pluck('branch_id')->toArray();

        $this->assertContains($this->branchAllowed->branch_id, $ids);
        $this->assertNotContains($this->branchSiblingDenied->branch_id, $ids);
        $this->assertNotContains($this->branchOtherCompany->branch_id, $ids);
        $this->assertCount(1, $ids);
    }

    #[Test]
    public function branch_list_under_denied_company_is_blocked_by_scope_guard(): void
    {
        // ScopeAccessGuard fail-closed: company_id in path is out of user's COMPANY scopes → 403
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/organization/companies/' . $this->companyDenied->company_id . '/branches');

        $response->assertStatus(403);
    }

    #[Test]
    public function show_denied_company_is_blocked_by_scope_guard(): void
    {
        // Out-of-scope COMPANY reference → ScopeAccessGuard denies (403), not data leak
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/organization/companies/' . $this->companyDenied->company_id);

        $response->assertStatus(403);
    }

    #[Test]
    public function show_denied_branch_is_blocked_by_scope_guard(): void
    {
        // Out-of-scope BRANCH reference → ScopeAccessGuard denies (403)
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/organization/branches/' . $this->branchSiblingDenied->branch_id);

        $response->assertStatus(403);
    }
}
