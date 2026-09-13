<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\UserCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

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
            'mobile' => '09120001122',
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

    #[Test]
    public function user_can_login_with_email_identifier_without_tenant_header(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/v1/identity-core/identity/auth/login', [
            'identifier' => $this->userEmail,
            'password'   => $this->plainPassword,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.requires_tenant_selection', false)
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
                    'active_tenant_id',
                ],
            ]);
    }

    #[Test]
    public function user_can_login_with_mobile_identifier(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/v1/identity-core/identity/auth/login', [
            'identifier' => '09120001122',
            'password'   => $this->plainPassword,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.requires_tenant_selection', false)
            ->assertJsonPath('data.active_tenant_id', $this->tenant->tenant_id);
    }

    #[Test]
    public function user_cannot_login_with_invalid_password(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/v1/identity-core/identity/auth/login', [
            'identifier' => $this->userEmail,
            'password'   => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['status' => 'error']);
    }

    #[Test]
    public function suspended_tenant_users_cannot_login(): void
    {
        $this->tenantUser->update(['status' => 2]);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/v1/identity-core/identity/auth/login', [
            'identifier' => $this->userEmail,
            'password'   => $this->plainPassword,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function legacy_email_field_still_accepted(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/v1/identity-core/identity/auth/login', [
            'email'    => $this->userEmail,
            'password' => $this->plainPassword,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.requires_tenant_selection', false);
    }
}
