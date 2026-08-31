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
            'identity.user.update',
            'identity.user.delete',
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

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function authorized_user_can_list_tenant_users(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/identity-core/identity/users');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    #[Test]
    public function authorized_user_can_create_tenant_user(): void
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

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/identity-core/identity/users', $payload);

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
            'event_type'     => 'identity.tenant_user.created.v1',
            'status'         => 1,
        ]);
    }

    #[Test]
    public function unauthorized_user_cannot_create_tenant_user(): void
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

    #[Test]
    public function create_tenant_user_validates_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/identity-core/identity/users', [
                'email' => 'incomplete@example.com',
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function cannot_add_same_user_twice_to_same_tenant(): void
    {
        $payload = [
            'email'      => 'duplicate@example.com',
            'password'   => 'SecurePass123!',
            'first_name' => 'Dup',
            'last_name'  => 'User',
        ];

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/identity-core/identity/users', $payload)
            ->assertStatus(201);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/identity-core/identity/users', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('status', 'error');
    }

    #[Test]
    public function authorized_user_can_show_tenant_user(): void
    {
        $membership = TenantUser::where('tenant_id', $this->tenant->tenant_id)
            ->where('user_id', $this->adminUser->user_id)
            ->first();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/identity-core/identity/users/' . $membership->tenant_user_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tenant_user_id', $membership->tenant_user_id);
    }

    #[Test]
    public function authorized_user_can_update_tenant_user(): void
    {
        $membership = TenantUser::where('tenant_id', $this->tenant->tenant_id)
            ->where('user_id', $this->adminUser->user_id)
            ->first();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/identity-core/identity/users/' . $membership->tenant_user_id, [
                'first_name' => 'Updated',
                'last_name'  => 'Admin',
                'status'     => 1,
                'is_owner'   => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('users', [
            'user_id'    => $this->adminUser->user_id,
            'first_name' => 'Updated',
            'last_name'  => 'Admin',
        ]);

        $this->assertDatabaseHas('tenant_users', [
            'tenant_user_id' => $membership->tenant_user_id,
            'is_owner'       => true,
            'status'         => 1,
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'      => $this->tenant->tenant_id,
            'aggregate_type' => 'tenant_users',
            'event_type'     => 'identity.tenant_user.updated.v1',
            'status'         => 1,
        ]);
    }

    #[Test]
    public function authorized_user_can_soft_delete_tenant_user(): void
    {
        $targetUser = User::factory()->create(['status' => 1]);
        $membership = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $targetUser->user_id,
            'status'    => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/identity-core/identity/users/' . $membership->tenant_user_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('tenant_users', [
            'tenant_user_id' => $membership->tenant_user_id,
        ]);

        // Physical row remains (soft delete only)
        $this->assertDatabaseHas('tenant_users', [
            'tenant_user_id' => $membership->tenant_user_id,
        ]);

        $show = $this->withHeaders($this->authHeaders())
            ->getJson('/api/identity-core/identity/users/' . $membership->tenant_user_id);

        $show->assertStatus(404);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'      => $this->tenant->tenant_id,
            'aggregate_type' => 'tenant_users',
            'event_type'     => 'identity.tenant_user.deleted.v1',
            'status'         => 1,
        ]);
    }

    #[Test]
    public function unauthorized_user_cannot_update_or_delete_tenant_user(): void
    {
        $otherUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $otherUser->user_id,
            'status'    => 1,
        ]);

        $token = $otherUser->createToken(
            'test-token-unauth-upd',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $membership = TenantUser::where('tenant_id', $this->tenant->tenant_id)
            ->where('user_id', $this->adminUser->user_id)
            ->first();

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];

        $this->withHeaders($headers)
            ->putJson('/api/identity-core/identity/users/' . $membership->tenant_user_id, [
                'first_name' => 'Hacked',
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->deleteJson('/api/identity-core/identity/users/' . $membership->tenant_user_id)
            ->assertStatus(403);
    }
}
