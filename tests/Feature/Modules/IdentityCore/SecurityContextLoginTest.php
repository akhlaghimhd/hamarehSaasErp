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
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUserScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Verifies that login returns full security_context per Architecture Law 4.4:
 * user_id, tenant_id, roles, scopes (+ permissions)
 */
class SecurityContextLoginTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected TenantUser $tenantUser;
    protected string $plainPassword = 'SecurePassword123!';
    protected string $userEmail = 'security.context@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'SEC_CTX',
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

        $this->tenantUser = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
            'is_owner'  => false,
        ]);

        // Role + Permission
        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'sec-role',
            'name'      => 'Security Role',
            'status'    => 1,
        ]);

        $permission = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenant->tenant_id,
            'code'                 => 'identity.user.view',
            'name'                 => 'View Users',
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

        // Scope (BRANCH)
        $scope = TenantScope::create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Main Branch Scope',
            'scope_type'   => 'BRANCH',
            'reference_id' => (string) Str::uuid(),
            'is_active'    => true,
        ]);

        TenantUserScope::create([
            'assignment_id'  => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $this->tenantUser->tenant_user_id,
            'scope_id'       => $scope->scope_id,
        ]);
    }

    /** @test */
    public function login_returns_full_security_context_per_law_4_4(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/login', [
            'email'     => $this->userEmail,
            'password'  => $this->plainPassword,
            'tenant_id' => $this->tenant->tenant_id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.security_context.user_id', $this->user->user_id)
            ->assertJsonPath('data.security_context.tenant_id', $this->tenant->tenant_id)
            ->assertJsonPath('data.security_context.tenant_user_id', $this->tenantUser->tenant_user_id)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'security_context' => [
                        'user_id',
                        'tenant_id',
                        'tenant_user_id',
                        'roles',
                        'permissions',
                        'scopes',
                        'is_owner',
                    ],
                ],
            ]);

        $context = $response->json('data.security_context');

        $this->assertNotEmpty($context['roles']);
        $this->assertContains('identity.user.view', $context['permissions']);
        $this->assertNotEmpty($context['scopes']);
        $this->assertEquals('BRANCH', $context['scopes'][0]['scope_type']);
    }

    /** @test */
    public function login_without_tenant_still_returns_user_but_empty_tenant_context(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/login', [
            'email'    => $this->userEmail,
            'password' => $this->plainPassword,
            // no tenant_id
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.security_context.user_id', $this->user->user_id)
            ->assertJsonPath('data.security_context.tenant_id', null)
            ->assertJsonPath('data.security_context.roles', [])
            ->assertJsonPath('data.security_context.scopes', []);
    }
}