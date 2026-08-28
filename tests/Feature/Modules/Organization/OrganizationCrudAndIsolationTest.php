<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Department;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Layer 3 – Organization CRUD + Tenant Isolation + basic Scope checks
 * Complements OrganizationPermissionTest and IdentityCore\ScopeIsolationTest
 */
class OrganizationCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        // --- Tenant A (authorized) ---
        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'ORG_CRUD_A',
            'status'      => 1,
        ]);

        $this->userA = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);

        $roleA = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'org-full',
            'name'      => 'Organization Full Access',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'organization.company.view',
            'organization.company.create',
            'organization.company.update',
            'organization.company.delete',
            'organization.branch.view',
            'organization.branch.create',
            'organization.branch.update',
            'organization.branch.delete',
            'organization.department.view',
            'organization.department.create',
            'organization.department.update',
            'organization.department.delete',
        ];

        foreach ($permissionCodes as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenantA->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Organization',
                'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
                'status'               => 1,
            ]);

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenantA->tenant_id,
                'tenant_role_id'            => $roleA->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenantA->tenant_id,
            'user_id'             => $this->userA->user_id,
            'tenant_role_id'      => $roleA->tenant_role_id,
        ]);

        $this->tokenA = $this->userA->createToken(
            'org-crud-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        // --- Tenant B (for isolation tests) ---
        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'ORG_CRUD_B',
            'status'      => 1,
        ]);

        $this->userB = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'user_id'   => $this->userB->user_id,
            'status'    => 1,
        ]);

        $this->tokenB = $this->userB->createToken(
            'org-crud-b',
            ['tenant:' . $this->tenantB->tenant_id]
        )->plainTextToken;

        // Default context = Tenant A
        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function authHeaders(string $token, string $tenantId): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $tenantId,
            'Accept'        => 'application/json',
        ];
    }

    // =========================================================================
    // COMPANY CRUD
    // =========================================================================

    /** @test */
    public function can_create_list_show_update_and_soft_delete_company(): void
    {
        // CREATE
        $createPayload = [
            'code'                => 'COMP-CRUD-01',
            'name'                => 'CRUD Company',
            'registration_number' => 'REG-100',
            'economic_code'       => 'ECO-100',
            'is_active'           => true,
        ];

        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/organization/companies', $createPayload);

        $createResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.code', 'COMP-CRUD-01')
            ->assertJsonPath('data.name', 'CRUD Company');

        $companyId = $createResponse->json('data.company_id');
        $this->assertNotEmpty($companyId);

        // INDEX
        $indexResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/organization/companies');

        $indexResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $ids = collect($indexResponse->json('data'))->pluck('company_id')->toArray();
        $this->assertContains($companyId, $ids);

        // SHOW
        $showResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/organization/companies/' . $companyId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.company_id', $companyId)
            ->assertJsonPath('data.code', 'COMP-CRUD-01');

        // UPDATE
        $updatePayload = [
            'code'                => 'COMP-CRUD-01-UPD',
            'name'                => 'CRUD Company Updated',
            'registration_number' => 'REG-200',
            'economic_code'       => 'ECO-200',
            'is_active'           => false,
        ];

        $updateResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/organization/companies/' . $companyId, $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.code', 'COMP-CRUD-01-UPD')
            ->assertJsonPath('data.name', 'CRUD Company Updated')
            ->assertJsonPath('data.is_active', false);

        // SOFT DELETE
        $deleteResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/organization/companies/' . $companyId);

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Record still exists but soft-deleted
        $this->assertSoftDeleted('erp_companies', [
            'company_id' => $companyId,
            'tenant_id'  => $this->tenantA->tenant_id,
        ]);

        // Should no longer appear in index
        $indexAfterDelete = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/organization/companies');

        $idsAfter = collect($indexAfterDelete->json('data'))->pluck('company_id')->toArray();
        $this->assertNotContains($companyId, $idsAfter);
    }

    /** @test */
    public function company_code_must_be_unique_within_same_tenant(): void
    {
        $payload = [
            'code'      => 'UNIQUE-CODE',
            'name'      => 'First Company',
            'is_active' => true,
        ];

        $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/organization/companies', $payload)
            ->assertStatus(201);

        // Same code in same tenant → must fail
        $duplicateResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/organization/companies', [
                'code'      => 'UNIQUE-CODE',
                'name'      => 'Second Company',
                'is_active' => true,
            ]);

        // Service throws Exception → expected 500 or handled as error (depending on exception handler)
        // We assert it does not return 201
        $this->assertNotEquals(201, $duplicateResponse->status());
    }

    /** @test */
    public function cannot_delete_company_that_has_branches(): void
    {
        $company = Company::create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'COMP-WITH-BR',
            'name'      => 'Company With Branch',
            'is_active' => true,
        ]);

        Branch::create([
            'tenant_id'  => $this->tenantA->tenant_id,
            'company_id' => $company->company_id,
            'code'       => 'BR-001',
            'name'       => 'Existing Branch',
            'is_active'  => true,
        ]);

        $response = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/organization/companies/' . $company->company_id);

        // Service throws → not successful delete
        $this->assertNotEquals(200, $response->status());
        $this->assertDatabaseHas('erp_companies', [
            'company_id' => $company->company_id,
            'deleted_at' => null,
        ]);
    }

    // =========================================================================
    // BRANCH CRUD
    // =========================================================================

    /** @test */
    public function can_create_list_show_update_and_soft_delete_branch(): void
    {
        $company = Company::create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'COMP-BR-CRUD',
            'name'      => 'Company For Branch CRUD',
            'is_active' => true,
        ]);

        // CREATE
        $createPayload = [
            'code'      => 'BR-CRUD-01',
            'name'      => 'Main Branch',
            'address'   => 'Tehran',
            'is_active' => true,
        ];

        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/organization/companies/' . $company->company_id . '/branches', $createPayload);

        $createResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.code', 'BR-CRUD-01');

        $branchId = $createResponse->json('data.branch_id');
        $this->assertNotEmpty($branchId);

        // SHOW
        $showResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/organization/branches/' . $branchId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.branch_id', $branchId);

        // UPDATE
        $updateResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/organization/branches/' . $branchId, [
                'code'      => 'BR-CRUD-01-UPD',
                'name'      => 'Main Branch Updated',
                'address'   => 'Isfahan',
                'is_active' => false,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.code', 'BR-CRUD-01-UPD')
            ->assertJsonPath('data.name', 'Main Branch Updated');

        // SOFT DELETE
        $deleteResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/organization/branches/' . $branchId);

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('erp_branches', [
            'branch_id' => $branchId,
            'tenant_id' => $this->tenantA->tenant_id,
        ]);
    }

    // =========================================================================
    // DEPARTMENT CRUD
    // =========================================================================

    /** @test */
    public function can_create_list_show_update_and_soft_delete_department(): void
    {
        $company = Company::create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'COMP-DEP-CRUD',
            'name'      => 'Company For Dept CRUD',
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'tenant_id'  => $this->tenantA->tenant_id,
            'company_id' => $company->company_id,
            'code'       => 'BR-DEP-CRUD',
            'name'       => 'Branch For Dept',
            'is_active'  => true,
        ]);

        // CREATE
        $createPayload = [
            'branch_id' => $branch->branch_id,
            'code'      => 'DEP-CRUD-01',
            'name'      => 'Sales Department',
            'is_active' => true,
        ];

        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/organization/companies/' . $company->company_id . '/departments', $createPayload);

        $createResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.code', 'DEP-CRUD-01');

        $departmentId = $createResponse->json('data.department_id');
        $this->assertNotEmpty($departmentId);

        // SHOW
        $showResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/organization/departments/' . $departmentId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.department_id', $departmentId);

        // UPDATE
        $updateResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/organization/departments/' . $departmentId, [
                'code'      => 'DEP-CRUD-01-UPD',
                'name'      => 'Sales Department Updated',
                'is_active' => false,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.code', 'DEP-CRUD-01-UPD');

        // SOFT DELETE
        $deleteResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/organization/departments/' . $departmentId);

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('erp_departments', [
            'department_id' => $departmentId,
            'tenant_id'     => $this->tenantA->tenant_id,
        ]);
    }

    // =========================================================================
    // TENANT ISOLATION
    // =========================================================================

    /** @test */
    public function tenant_a_cannot_see_or_modify_companies_of_tenant_b(): void
    {
        // Data belonging to Tenant B
        $companyB = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenantB->tenant_id,
            'code'       => 'COMP-B-ONLY',
            'name'       => 'Tenant B Company',
            'is_active'  => true,
        ]);

        // Tenant A tries to list → must not see Tenant B data
        $indexResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/organization/companies');

        $indexResponse->assertStatus(200);
        $ids = collect($indexResponse->json('data'))->pluck('company_id')->toArray();
        $this->assertNotContains($companyB->company_id, $ids);

        // Tenant A tries to show Tenant B company → 404
        $showResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/organization/companies/' . $companyB->company_id);

        $showResponse->assertStatus(404);

        // Tenant A tries to update Tenant B company → must fail
        $updateResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/organization/companies/' . $companyB->company_id, [
                'code'      => 'HACKED',
                'name'      => 'Hacked Name',
                'is_active' => true,
            ]);

        $this->assertNotEquals(200, $updateResponse->status());

        // Data of Tenant B remains untouched
        $this->assertDatabaseHas('erp_companies', [
            'company_id' => $companyB->company_id,
            'tenant_id'  => $this->tenantB->tenant_id,
            'code'       => 'COMP-B-ONLY',
            'name'       => 'Tenant B Company',
        ]);
    }

    /** @test */
    public function tenant_a_cannot_create_company_under_tenant_b_context(): void
    {
        // Even if someone tries to force wrong tenant header, context middleware + TenantScoped protect
        $response = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantB->tenant_id))
            ->postJson('/api/organization/companies', [
                'code'      => 'CROSS-TENANT',
                'name'      => 'Should Not Be Created',
                'is_active' => true,
            ]);

        // Depending on middleware strictness this may be 401/403 or still isolated
        // Critical: no record must be created under tenant B by user A
        $this->assertDatabaseMissing('erp_companies', [
            'code'      => 'CROSS-TENANT',
            'tenant_id' => $this->tenantB->tenant_id,
        ]);
    }
}