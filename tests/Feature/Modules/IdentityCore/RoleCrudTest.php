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
            'identity.role.assign-permissions',
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
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'TARGET_ROLE',
            'name'      => 'Target Role',
            'status'    => 1,
        ]);

        $this->token = $this->user->createToken('test', ['tenant:' . $this->tenant->tenant_id])->plainTextToken;
        app(TenantContext::class)->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
    }

    private function authHeaders(): array
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
            ->getJson('/api/v1/identity-core/identity/roles/' . $this->existingRole->tenant_role_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tenant_role_id', $this->existingRole->tenant_role_id)
            ->assertJsonPath('data.code', 'TARGET_ROLE');
    }

    #[Test]
    public function authorized_user_can_update_role(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/identity-core/identity/roles/' . $this->existingRole->tenant_role_id, [
                'name'        => 'Target Role Updated',
                'description' => 'Updated description',
                'status'      => 1,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Target Role Updated')
            ->assertJsonPath('data.description', 'Updated description');
    }

    #[Test]
    public function authorized_user_can_soft_delete_role(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v1/identity-core/identity/roles/' . $this->existingRole->tenant_role_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('tenant_roles', [
            'tenant_role_id' => $this->existingRole->tenant_role_id,
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
        $token = $other->createToken('test', ['tenant:' . $this->tenant->tenant_id])->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];

        $this->withHeaders($headers)
            ->putJson('/api/v1/identity-core/identity/roles/' . $this->existingRole->tenant_role_id, [
                'name' => 'Hacked',
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/identity-core/identity/roles/' . $this->existingRole->tenant_role_id)
            ->assertStatus(403);
    }

    #[Test]
    public function create_role_rejects_duplicate_name_in_tenant(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/identity-core/identity/roles', [
                'role_name' => 'Target Role',
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function update_role_rejects_duplicate_name_in_tenant(): void
    {
        $other = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'OTHER_ROLE',
            'name'      => 'Other Role',
            'status'    => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/identity-core/identity/roles/' . $other->tenant_role_id, [
                'name' => 'Target Role',
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function cannot_assign_permissions_to_inactive_role(): void
    {
        $this->existingRole->update(['status' => 0]);

        $perm = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenant->tenant_id,
            'code'                 => 'identity.test.perm',
            'name'                 => 'Test Perm',
            'module_name'          => 'Identity',
            'status'               => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson(
                '/api/v1/identity-core/identity/roles/' . $this->existingRole->tenant_role_id . '/permissions',
                ['permission_ids' => [$perm->tenant_permission_id]]
            );

        $response->assertStatus(422);
    }
}
