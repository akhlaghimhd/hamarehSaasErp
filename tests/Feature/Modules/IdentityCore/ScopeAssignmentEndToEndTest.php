<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Scope assignment operational path (Phase C):
 * create BRANCH scope → assign → userScopes API → login → branch list filtered → unassign.
 */
class ScopeAssignmentEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $scopedUser;
    protected TenantUser $scopedTenantUser;
    protected string $adminToken;
    protected string $plainPassword = 'SecurePassword123!';
    protected Branch $branchAllowed;
    protected Branch $branchOther;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scope.enforcement_mode' => 'gradual']);

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'SCOPE_E2E',
            'status'      => 1,
        ]);

        $this->adminUser = User::factory()->create(['status' => 1, 'email' => 'scope.admin@example.com']);
        $this->scopedUser = User::factory()->create(['status' => 1, 'email' => 'scope.user@example.com']);

        foreach ([$this->adminUser, $this->scopedUser] as $u) {
            UserCredential::create([
                'credential_id'       => (string) Str::uuid(),
                'user_id'             => $u->user_id,
                'password_hash'       => Hash::make($this->plainPassword),
                'authentication_type' => 1,
                'is_verified'         => true,
                'two_factor_enabled'  => false,
                'failed_login_count'  => 0,
            ]);

            TenantUser::factory()->create([
                'tenant_id' => $this->tenant->tenant_id,
                'user_id'   => $u->user_id,
                'status'    => 1,
            ]);
        }

        $this->scopedTenantUser = TenantUser::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('user_id', $this->scopedUser->user_id)
            ->first();

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'scope-admin',
            'status'    => 1,
        ]);

        foreach ([
            'identity.scope.create',
            'identity.scope.assign',
            'identity.scope.view',
            'organization.branch.view',
            'organization.branch.create',
            'organization.company.create',
            'organization.company.view',
        ] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Identity',
                'status'               => 1,
            ]);

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenant->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        // Admin role
        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->adminUser->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        // Scoped user also needs branch.view for list assertions
        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->scopedUser->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->adminToken = $this->adminUser->createToken(
            'scope-admin',
            ['*', 'tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->company = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'CO-E2E',
            'name'       => 'E2E Company',
            'is_active'  => true,
        ]);

        $this->branchAllowed = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->company->company_id,
            'code'       => 'BR-OK',
            'name'       => 'Allowed Branch',
            'is_active'  => true,
        ]);

        $this->branchOther = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->company->company_id,
            'code'       => 'BR-NO',
            'name'       => 'Other Branch',
            'is_active'  => true,
        ]);
    }

    protected function adminHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    /** @test */
    public function create_assign_list_filter_and_unassign_scopes_end_to_end(): void
    {
        // 1) Create BRANCH scope bound to real branch
        $create = $this->withHeaders($this->adminHeaders())->postJson('/api/identity-core/identity/scopes', [
            'scope_name'   => 'Allowed Branch Scope',
            'scope_type'   => 'BRANCH',
            'reference_id' => $this->branchAllowed->branch_id,
            'is_active'    => true,
        ]);

        $create->assertStatus(201);
        $scopeId = $create->json('data.scope_id');
        $this->assertNotEmpty($scopeId);

        // 2) Reject fake reference_id
        $bad = $this->withHeaders($this->adminHeaders())->postJson('/api/identity-core/identity/scopes', [
            'scope_name'   => 'Bad',
            'scope_type'   => 'BRANCH',
            'reference_id' => (string) Str::uuid(),
        ]);

        $bad->assertStatus(400);

        // 3) Assign to scoped tenant user
        $assign = $this->withHeaders($this->adminHeaders())->postJson('/api/identity-core/identity/scopes/assign', [
            'tenant_user_id' => $this->scopedTenantUser->tenant_user_id,
            'scope_ids'      => [$scopeId],
        ]);

        $assign->assertStatus(200);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => 'identity.scope.assigned.v1',
        ]);

        // 4) userScopes API (must not be captured by /scopes/{id})
        $userScopes = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/identity-core/identity/scopes/user/' . $this->scopedTenantUser->tenant_user_id);

        $userScopes->assertStatus(200);
        $userScopeIds = collect($userScopes->json('data'))->pluck('scope_id')->toArray();
        $this->assertContains($scopeId, $userScopeIds);

        // 5) Login as scoped user → scopes in security_context
        $login = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/login', [
            'email'     => 'scope.user@example.com',
            'password'  => $this->plainPassword,
            'tenant_id' => $this->tenant->tenant_id,
        ]);

        $login->assertStatus(200);
        $scopes = $login->json('data.security_context.scopes');
        $this->assertNotEmpty($scopes);
        $this->assertSame('BRANCH', $scopes[0]['scope_type']);
        $this->assertSame($this->branchAllowed->branch_id, $scopes[0]['reference_id']);

        $userToken = $login->json('data.access_token');
        $this->assertNotEmpty($userToken);

        // 6) Branch list filtered by assigned scope (Organization API)
        $list = $this->withHeaders([
            'Authorization' => 'Bearer ' . $userToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->getJson('/api/organization/companies/' . $this->company->company_id . '/branches');

        $list->assertStatus(200);
        $branchIds = collect($list->json('data'))->pluck('branch_id')->toArray();
        $this->assertContains($this->branchAllowed->branch_id, $branchIds);
        $this->assertNotContains($this->branchOther->branch_id, $branchIds);

        // 7) Unassign
        $unassign = $this->withHeaders($this->adminHeaders())->postJson('/api/identity-core/identity/scopes/unassign', [
            'tenant_user_id' => $this->scopedTenantUser->tenant_user_id,
            'scope_ids'      => [$scopeId],
        ]);

        $unassign->assertStatus(200);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => 'identity.scope.unassigned.v1',
        ]);

        $this->assertSame(
            0,
            (int) DB::table('tenant_user_scopes')
                ->where('tenant_user_id', $this->scopedTenantUser->tenant_user_id)
                ->where('scope_id', $scopeId)
                ->whereNull('deleted_at')
                ->count()
        );
    }
}
