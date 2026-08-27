<?php

namespace Tests\Feature\Modules\SaasPlatform;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class TenantPermissionTest extends TestCase
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
            'tenant_code' => 'PERM_SAAS',
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
            'code'      => 'saas-admin-manager',
            'name'      => 'SaaS Admin Manager',
            'status'    => 1,
        ]);

        $createPerm = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenant->tenant_id,
            'code'                 => 'saas-admin.tenant.create',
            'name'                 => 'Create Tenant',
            'module_name'          => 'SaasAdmin',
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
            'test-token-auth-' . $this->tenant->tenant_id,
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->unauthorizedUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->unauthorizedUser->user_id,
            'status'    => 1,
        ]);

        $this->unauthorizedToken = $this->unauthorizedUser->createToken(
            'test-token-unauth-' . $this->tenant->tenant_id,
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
    }

    /** @test */
    public function authorized_user_with_permission_can_create_tenant(): void
    {
        $payload = [
            'tenant_code' => 'NEW_ORG_001',
            'tenant_name' => 'New Organization Ltd',
            'legal_name'  => 'New Organization Legal Name',
            'slug'        => 'new-org-001',
            'tenant_type' => 1,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/saas-platform/tenants', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tenant_code', 'NEW_ORG_001')
            ->assertJsonPath('data.tenant_name', 'New Organization Ltd')
            ->assertJsonPath('data.slug', 'new-org-001');

        $this->assertDatabaseHas('tenants', [
            'tenant_code' => 'NEW_ORG_001',
            'tenant_name' => 'New Organization Ltd',
            'slug'        => 'new-org-001',
            'status'      => 1,
        ]);

        $newTenantId = $response->json('data.tenant_id');

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $newTenantId,
            'user_id'   => $this->authorizedUser->user_id,
            'is_owner'  => true,
            'status'    => 1,
        ]);
    }

    /** @test */
    public function unauthorized_user_without_permission_cannot_create_tenant(): void
    {
        $payload = [
            'tenant_code' => 'FORBIDDEN_ORG',
            'tenant_name' => 'Forbidden Organization',
            'slug'        => 'forbidden-org',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->unauthorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/saas-platform/tenants', $payload);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseMissing('tenants', [
            'tenant_code' => 'FORBIDDEN_ORG',
        ]);
    }

    /** @test */
    public function unauthenticated_request_is_rejected(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/saas-platform/tenants', [
            'tenant_code' => 'NO_AUTH',
            'tenant_name' => 'No Auth Org',
            'slug'        => 'no-auth-org',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function create_tenant_validates_required_fields(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/saas-platform/tenants', [
            'tenant_name' => 'Missing Code And Slug',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function create_tenant_rejects_duplicate_tenant_code(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/saas-platform/tenants', [
            'tenant_code' => 'DUP_CODE',
            'tenant_name' => 'First Org',
            'slug'        => 'first-org',
        ])->assertStatus(201);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/saas-platform/tenants', [
            'tenant_code' => 'DUP_CODE',
            'tenant_name' => 'Second Org',
            'slug'        => 'second-org',
        ]);

        $response->assertStatus(422);
    }
}