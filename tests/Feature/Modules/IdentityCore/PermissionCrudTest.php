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

class PermissionCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;
    protected TenantPermission $targetPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'PERM_CRUD',
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
            'code'      => 'perm-crud-manager',
            'name'      => 'Permission CRUD Manager',
            'status'    => 1,
        ]);

        foreach ([
            'identity.permission.view',
            'identity.permission.create',
            'identity.permission.update',
            'identity.permission.delete',
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

        $this->targetPermission = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenant->tenant_id,
            'code'                 => 'identity.target.action',
            'name'                 => 'Target Action',
            'module_name'          => 'Identity',
            'action_type'          => 'EXECUTE',
            'description'          => 'Original',
            'status'               => 1,
        ]);

        $this->token = $this->user->createToken(
            'perm-crud',
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
    public function authorized_user_can_show_permission(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/identity-core/identity/permissions/' . $this->targetPermission->tenant_permission_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tenant_permission_id', $this->targetPermission->tenant_permission_id)
            ->assertJsonPath('data.code', 'identity.target.action');
    }

    #[Test]
    public function authorized_user_can_update_permission(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/identity-core/identity/permissions/' . $this->targetPermission->tenant_permission_id, [
                'name'        => 'Target Action Updated',
                'description' => 'Updated description',
                'status'      => 1,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Target Action Updated')
            ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('tenant_permissions', [
            'tenant_permission_id' => $this->targetPermission->tenant_permission_id,
            'name'                 => 'Target Action Updated',
            'description'          => 'Updated description',
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => 'identity.permission.updated.v1',
            'status'     => 1,
        ]);
    }

    #[Test]
    public function authorized_user_can_soft_delete_permission(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/identity-core/identity/permissions/' . $this->targetPermission->tenant_permission_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('tenant_permissions', [
            'tenant_permission_id' => $this->targetPermission->tenant_permission_id,
            'tenant_id'            => $this->tenant->tenant_id,
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => 'identity.permission.deleted.v1',
            'status'     => 1,
        ]);
    }

    #[Test]
    public function unauthorized_user_cannot_update_or_delete_permission(): void
    {
        $other = User::factory()->create(['status' => 1]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $other->user_id,
            'status'    => 1,
        ]);

        $token = $other->createToken(
            'perm-unauth',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];

        $this->withHeaders($headers)
            ->putJson('/api/identity-core/identity/permissions/' . $this->targetPermission->tenant_permission_id, [
                'name' => 'Hacked',
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->deleteJson('/api/identity-core/identity/permissions/' . $this->targetPermission->tenant_permission_id)
            ->assertStatus(403);
    }
}
