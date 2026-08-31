<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * F4: Authorization source of truth is DB at request time, not token snapshot.
 */
class AuthorizationSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $plainPassword = 'SecurePassword123!';
    protected string $userEmail = 'auth.sot@example.com';
    protected string $token;
    protected string $tenantRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'AUTH_SOT',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create([
            'email'  => $this->userEmail,
            'status' => 1,
        ]);

        UserCredential::create([
            'credential_id'       => (string) Str::uuid(),
            'user_id'             => $this->user->user_id,
            'password_hash'       => Hash::make($this->plainPassword),
            'authentication_type' => 1,
            'is_verified'         => true,
            'two_factor_enabled'  => false,
            'failed_login_count'  => 0,
        ]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'sot-role',
            'name'      => 'SoT Role',
            'status'    => 1,
        ]);

        $this->tenantRoleId = $role->tenant_role_id;

        $permission = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenant->tenant_id,
            'code'                 => 'identity.role.view',
            'name'                 => 'View Roles',
            'module_name'          => 'Identity',
            'status'               => 1,
        ]);

        TenantRolePermission::create([
            'tenant_role_permission_id' => (string) Str::uuid(),
            'tenant_id'                 => $this->tenant->tenant_id,
            'tenant_role_id'            => $role->tenant_role_id,
            'tenant_permission_id'      => $permission->tenant_permission_id,
        ]);

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->user->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $login = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/login', [
            'email'     => $this->userEmail,
            'password'  => $this->plainPassword,
            'tenant_id' => $this->tenant->tenant_id,
        ]);

        $login->assertStatus(200);
        $this->token = $login->json('data.access_token');
        $this->assertNotEmpty($this->token);
        $this->assertContains('identity.role.view', $login->json('data.security_context.permissions'));
    }

    #[Test]
    public function same_token_can_access_while_permission_exists_in_db(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->getJson('/api/identity-core/identity/roles');

        $response->assertStatus(200);
    }

    #[Test]
    public function same_token_is_denied_after_permission_revoked_in_db(): void
    {
        $deletedRp = DB::table('tenant_role_permissions')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('tenant_role_id', $this->tenantRoleId)
            ->delete();

        $deletedUr = DB::table('tenant_user_roles')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('user_id', $this->user->user_id)
            ->delete();

        $this->assertGreaterThan(0, $deletedRp, 'role-permission rows must be deleted');
        $this->assertGreaterThan(0, $deletedUr, 'user-role rows must be deleted');

        $this->assertSame(
            0,
            (int) DB::table('tenant_role_permissions')->where('tenant_id', $this->tenant->tenant_id)->count()
        );
        $this->assertSame(
            0,
            (int) DB::table('tenant_user_roles')->where('tenant_id', $this->tenant->tenant_id)->where('user_id', $this->user->user_id)->count()
        );

        Cache::flush();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->getJson('/api/identity-core/identity/roles');

        $response->assertStatus(403);
    }
}
