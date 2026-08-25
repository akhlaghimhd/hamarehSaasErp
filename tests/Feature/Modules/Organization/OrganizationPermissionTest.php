<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

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

        // Authorized user with organization.company.create
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

        $createPerm = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenant->tenant_id,
            'code'                 => 'organization.company.create',
            'name'                 => 'Create Company',
            'module_name'          => 'Organization',
            'action_type'          => 'CREATE',
            'status'               => 1,
        ]);

        TenantRolePermission::create([
            'tenant_role_permission_id' => (string) Str::uuid(),
            'tenant_id'                 => $this->tenant->tenant_id,
            'tenant_role_id'            => $role->tenant_role_id,
            'tenant_permission_id'      => $createPerm->tenant_permission_id,
        ]);

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

        // Unauthorized user (same tenant, no permission)
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

    /** @test */
    public function authorized_user_with_permission_can_create_company(): void
    {
        $payload = [
            'code'                 => 'COMP-001',
            'name'                 => 'Test Company',
            'registration_number'  => '123456',
            'economic_code'        => 'ECO-001',
            'is_active'            => true,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/organization/companies', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');
    }

    /** @test */
    public function unauthorized_user_without_permission_cannot_create_company(): void
    {
        $payload = [
            'code' => 'FORBIDDEN',
            'name' => 'Forbidden Company',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->unauthorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/organization/companies', $payload);

        $response->assertStatus(403);
    }

    /** @test */
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
}