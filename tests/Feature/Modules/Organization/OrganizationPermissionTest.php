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
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class OrganizationPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $authorizedUser;
    protected User $unauthorizedUser;
    protected string $authorizedToken;
    protected string $unauthorizedToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'ORG_PERM',
            'status'      => 1,
        ]);

        $this->authorizedUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->authorizedUser->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'org-manager',
            'name'      => 'Organization Manager',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'organization.company.create',
            'organization.branch.create',
            'organization.department.create',
        ];

        foreach ($permissionCodes as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Organization',
                'action_type'          => 'CREATE',
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
            'user_id'             => $this->authorizedUser->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->authorizedToken = $this->authorizedUser->createToken(
            'org-auth-token',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->unauthorizedUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->unauthorizedUser->user_id,
            'status'    => 1,
        ]);

        $this->unauthorizedToken = $this->unauthorizedUser->createToken(
            'org-unauth-token',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
    }

    #[Test]
    public function authorized_user_with_permission_can_create_company(): void
    {
        $payload = [
            'code'                => 'COMP-001',
            'name'                => 'Test Company',
            'registration_number' => '123456',
            'economic_code'       => 'ECO-001',
            'is_active'           => true,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/organization/companies', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');
    }

    #[Test]
    public function unauthorized_user_without_permission_cannot_create_company(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->unauthorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/organization/companies', [
            'code' => 'FORBIDDEN',
            'name' => 'Forbidden Company',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/organization/companies', [
            'code' => 'NOAUTH',
            'name' => 'No Auth Company',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function authorized_user_with_permission_can_create_branch(): void
    {
        $company = Company::create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'COMP-BR',
            'name'      => 'Company For Branch',
            'is_active' => true,
        ]);

        $payload = [
            'company_id' => $company->company_id,
            'code'       => 'BR-001',
            'name'       => 'Main Branch',
            'address'    => 'Tehran',
            'is_active'  => true,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/organization/companies/' . $company->company_id . '/branches', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');
    }

    #[Test]
    public function unauthorized_user_cannot_create_branch(): void
    {
        $company = Company::create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'COMP-BR2',
            'name'      => 'Company For Forbidden Branch',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->unauthorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/organization/companies/' . $company->company_id . '/branches', [
            'company_id' => $company->company_id,
            'code'       => 'BR-FORB',
            'name'       => 'Forbidden Branch',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function authorized_user_with_permission_can_create_department(): void
    {
        $company = Company::create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'COMP-DEP',
            'name'      => 'Company For Department',
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $company->company_id,
            'code'       => 'BR-DEP',
            'name'       => 'Branch For Department',
            'is_active'  => true,
        ]);

        $payload = [
            'branch_id' => $branch->branch_id,
            'code'      => 'DEP-001',
            'name'      => 'Sales Department',
            'is_active' => true,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/organization/companies/' . $company->company_id . '/departments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');
    }

    #[Test]
    public function unauthorized_user_cannot_create_department(): void
    {
        $company = Company::create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'COMP-DEP2',
            'name'      => 'Company For Forbidden Dept',
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $company->company_id,
            'code'       => 'BR-DEP2',
            'name'       => 'Branch For Forbidden Dept',
            'is_active'  => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->unauthorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/organization/companies/' . $company->company_id . '/departments', [
            'branch_id' => $branch->branch_id,
            'code'      => 'DEP-FORB',
            'name'      => 'Forbidden Department',
        ]);

        $response->assertStatus(403);
    }
}
