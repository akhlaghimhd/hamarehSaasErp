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

class RoleCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;
    protected TenantRole $existingRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'ROLE_CRUD',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'role-manager',
            'name'      => 'Role Manager',
            'status'    => 1,
        ]);

        foreach ([
            'identity.role.view',
            'identity.role.create',
            'identity.role.update',
            'identity.role.delete',
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

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->user->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->existingRole = TenantRole::factory()->create([
            'tenant_id'   => $this->tenant->tenant_id,
            'code'        => 'TARGET_ROLE',
            'name'        => 'Target Role',
            'description' => 'Original',
            'status'      => 1,
        ]);

        $this->token = $this->user->createToken(
            'role-crud',
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
    public function authorized_user_can_show_role(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/identity-core/identity/roles/' . $this->existingRole->tenant_role_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tenant_role_id', $this->existingRole->tenant_role_id)
            ->assertJsonPath('data.code', 'TARGET_ROLE');
    }

    #[Test]
    public function authorized_user_can_update_role(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/identity-core/identity/roles/' . $this->existingRole->tenant_role_id, [
                'name'        => 'Target Role Updated',
                'description' => 'Updated description',
                'status'      => 1,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Target Role Updated')
            ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('tenant_roles', [
            'tenant_role_id' => $this->existingRole->tenant_role_id,
            'name'           => 'Target Role Updated',
            'description'    => 'Updated description',
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => 'identity.role.updated.v1',
            'status'     => 1,
        ]);
    }

    #[Test]
    public function authorized_user_can_soft_delete_role(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/identity-core/identity/roles/' . $this->existingRole->tenant_role_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('tenant_roles', [
            'tenant_role_id' => $this->existingRole->tenant_role_id,
            'tenant_id'      => $this->tenant->tenant_id,
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => 'identity.role.deleted.v1',
            'status'     => 1,
        ]);
    }

    #[Test]
    public function unauthorized_user_cannot_update_or_delete_role(): void
    {
        $other = User::factory()->create(['status' => 1]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $other->user_id,
            'status'    => 1,
        ]);

        $token = $other->createToken(
            'role-unauth',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];

        $this->withHeaders($headers)
            ->putJson('/api/identity-core/identity/roles/' . $this->existingRole->tenant_role_id, [
                'name' => 'Hacked',
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->deleteJson('/api/identity-core/identity/roles/' . $this->existingRole->tenant_role_id)
            ->assertStatus(403);
    }
}
