<?php

namespace Tests\Feature\Modules\IdentityCore;

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
use PHPUnit\Framework\Attributes\Test;

class PermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected TenantRole $role;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'PERM_TEST',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        $this->role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'perm-manager',
            'name'      => 'Permission Manager',
            'status'    => 1,
        ]);

        $createPerm = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenant->tenant_id,
            'code'                 => 'identity.permission.create',
            'name'                 => 'Create Permission',
            'module_name'          => 'Identity',
            'action_type'          => 'CREATE',
            'status'               => 1,
        ]);

        $viewPerm = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenant->tenant_id,
            'code'                 => 'identity.permission.view',
            'name'                 => 'View Permissions',
            'module_name'          => 'Identity',
            'action_type'          => 'READ',
            'status'               => 1,
        ]);

        TenantRolePermission::create([
            'tenant_role_permission_id' => (string) Str::uuid(),
            'tenant_id'                 => $this->tenant->tenant_id,
            'tenant_role_id'            => $this->role->tenant_role_id,
            'tenant_permission_id'      => $createPerm->tenant_permission_id,
        ]);

        TenantRolePermission::create([
            'tenant_role_permission_id' => (string) Str::uuid(),
            'tenant_id'                 => $this->tenant->tenant_id,
            'tenant_role_id'            => $this->role->tenant_role_id,
            'tenant_permission_id'      => $viewPerm->tenant_permission_id,
        ]);

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->user->user_id,
            'tenant_role_id'      => $this->role->tenant_role_id,
        ]);

        $this->token = $this->user->createToken(
            'test-token-' . $this->tenant->tenant_id,
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
    }

    #[Test]
    public function authorized_user_can_create_permission(): void
    {
        $payload = [
            'code'        => 'identity.test.action',
            'name'        => 'Test Action',
            'module_name' => 'Identity',
            'action_type' => 'EXECUTE',
            'description' => 'A test permission',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/permissions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.code', 'identity.test.action')
            ->assertJsonPath('data.module_name', 'Identity');

        $this->assertDatabaseHas('tenant_permissions', [
            'tenant_id'   => $this->tenant->tenant_id,
            'code'        => 'identity.test.action',
            'name'        => 'Test Action',
            'module_name' => 'Identity',
            'status'      => 1,
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'      => $this->tenant->tenant_id,
            'aggregate_type' => 'tenant_permissions',
            'event_type'     => 'identity.permission.created.v1',
            'status'         => 1,
        ]);
    }

    #[Test]
    public function unauthorized_user_cannot_create_permission(): void
    {
        $otherUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $otherUser->user_id,
            'status'    => 1,
        ]);

        $token = $otherUser->createToken(
            'test-token-unauth',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/permissions', [
            'code'        => 'identity.forbidden.action',
            'name'        => 'Forbidden Action',
            'module_name' => 'Identity',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    #[Test]
    public function create_permission_validates_code_format(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/permissions', [
            'code'        => 'InvalidCodeFormat',
            'name'        => 'Invalid',
            'module_name' => 'Identity',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function create_permission_rejects_duplicate_code_in_same_tenant(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/permissions', [
            'code'        => 'identity.duplicate.test',
            'name'        => 'Duplicate Test',
            'module_name' => 'Identity',
        ])->assertStatus(201);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/permissions', [
            'code'        => 'identity.duplicate.test',
            'name'        => 'Duplicate Test Again',
            'module_name' => 'Identity',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function authorized_user_can_list_permissions(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->getJson('/api/identity-core/identity/permissions');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $codes = collect($data)->pluck('code')->toArray();
        $this->assertContains('identity.permission.create', $codes);
        $this->assertContains('identity.permission.view', $codes);
    }
}
