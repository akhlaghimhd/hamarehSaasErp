<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\UserCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $globalUser;
    protected TenantUser $tenantUser;
    protected string $plainPassword = 'SecurePassword123!';
    protected string $userEmail = 'demo.admin@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'DEMO_ORG',
            'status'      => 1,
        ]);

        $this->globalUser = User::factory()->create([
            'email'  => $this->userEmail,
            'status' => 1,
        ]);

        UserCredential::create([
            'credential_id'       => (string) Str::uuid(),
            'user_id'             => $this->globalUser->user_id,
            'password_hash'       => Hash::make($this->plainPassword),
            'authentication_type' => 1,
            'is_verified'         => true,
            'two_factor_enabled'  => false,
            'failed_login_count'  => 0,
        ]);

        $this->tenantUser = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->globalUser->user_id,
            'status'    => 1,
        ]);
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
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
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'user' => [
                        'user_id',
                        'tenant_user_id',
                    ],
                ],
            ]);
    }

    /** @test */
    public function user_cannot_login_with_invalid_password()
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/login', [
            'email'     => $this->userEmail,
            'password'  => 'WrongPassword!',
            'tenant_id' => $this->tenant->tenant_id,
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['status' => 'error']);
    }

    /** @test */
    public function suspended_tenant_users_cannot_login()
    {
        $this->tenantUser->update(['status' => 2]); // 2: Suspended

        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/login', [
            'email'     => $this->userEmail,
            'password'  => $this->plainPassword,
            'tenant_id' => $this->tenant->tenant_id,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Your account is suspended in this organization.',
            ]);
    }

    /** @test */
    public function user_cannot_login_to_unassociated_tenant()
    {
        $otherTenant = Tenant::factory()->create(['tenant_code' => 'OTHER_ORG']);

        $response = $this->withHeaders([
            'X-Tenant-ID' => $otherTenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/login', [
            'email'     => $this->userEmail,
            'password'  => $this->plainPassword,
            'tenant_id' => $otherTenant->tenant_id,
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['status' => 'error']);
    }
}