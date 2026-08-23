<?php

namespace Tests\Feature\Modules\IdentityCore;

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

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected TenantRole $role;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'USER_MGMT',
            'status'      => 1,
        ]);

        $this->adminUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->adminUser->user_id,
            'status'    => 1,
        ]);

        $this->role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'user-manager',
            'name'      => 'User Manager',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'identity.user.view',
            'identity.user.create',
            'identity.role.assign',
        ];

        foreach ($permissionCodes as $code) {
            $permission = TenantPermission::create([
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
                'tenant_role_id'            => $this->role->tenant_role_id,
                'tenant_permission_id'      => $permission->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->adminUser->user_id,
            'tenant_role_id'      => $this->role->tenant_role_id,
        ]);

        $this->token = $this->adminUser->createToken(
            'test-token-' . $this->tenant->tenant_id,
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
    }

    /** @test */
    public function authorized_user_can_list_tenant_users()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->getJson('/api/identity-core/identity/users');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /** @test */
    public function authorized_user_can_create_tenant_user()
    {
        $payload = [
            'email'      => 'new.user@example.com',
            'password'   => 'SecurePass123!',
            'first_name' => 'New',
            'last_name'  => 'User',
            'mobile'     => '09121234567',
            'is_owner'   => false,
            'role_ids'   => [$this->role->tenant_role_id],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.email', 'new.user@example.com');

        $this->assertDatabaseHas('users', [
            'email'      => 'new.user@example.com',
            'first_name' => 'New',
            'last_name'  => 'User',
        ]);

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $this->tenant->tenant_id,
            'status'    => 1,
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'      => $this->tenant->tenant_id,
            'aggregate_type' => 'tenant_users',
            'event_type'     => 'identity.tenant_user.created',
            'status'         => 1,
        ]);
    }

    /** @test */
    public function unauthorized_user_cannot_create_tenant_user()
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
        ])->postJson('/api/identity-core/identity/users', [
            'email'      => 'forbidden@example.com',
            'password'   => 'SecurePass123!',
            'first_name' => 'Forbidden',
            'last_name'  => 'User',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    /** @test */
    public function create_tenant_user_validates_required_fields()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/users', [
            'email' => 'incomplete@example.com',
            // missing password, first_name, last_name
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function cannot_add_same_user_twice_to_same_tenant()
    {
        $payload = [
            'email'      => 'duplicate@example.com',
            'password'   => 'SecurePass123!',
            'first_name' => 'Dup',
            'last_name'  => 'User',
        ];

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/users', $payload)
            ->assertStatus(201);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/users', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('status', 'error');
    }

    /** @test */
    public function authorized_user_can_show_tenant_user()
    {
        $membership = TenantUser::where('tenant_id', $this->tenant->tenant_id)
            ->where('user_id', $this->adminUser->user_id)
            ->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->getJson('/api/identity-core/identity/users/' . $membership->tenant_user_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tenant_user_id', $membership->tenant_user_id);
    }
}