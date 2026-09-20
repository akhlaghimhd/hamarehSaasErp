<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\IdentityLoginOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class PasswordPolicyAndSetPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $mobile = '09123334455';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'PWD_POL',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create([
            'first_name' => 'Ali',
            'last_name'  => 'Rezaei',
            'email'      => 'ali.rezaei@pwdpol.erp.ir',
            'mobile'     => $this->mobile,
            'status'     => 1,
        ]);

        UserCredential::create([
            'credential_id'       => (string) Str::uuid(),
            'user_id'             => $this->user->user_id,
            'password_hash'       => null,
            'must_set_password'   => true,
            'authentication_type' => 2,
            'is_verified'         => false,
            'two_factor_enabled'  => false,
            'failed_login_count'  => 0,
        ]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);
    }

    #[Test]
    public function new_user_cannot_login_with_password_must_use_otp(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/identity-core/identity/auth/login', [
                'identifier' => $this->mobile,
                'password'   => 'Anything1!',
            ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['status' => 'error']);
    }

    #[Test]
    public function otp_verify_returns_must_set_password_limited_token(): void
    {
        $plain = '123456';
        IdentityLoginOtp::create([
            'otp_id'        => (string) Str::uuid(),
            'mobile'        => $this->mobile,
            'code_hash'     => Hash::make($plain),
            'expires_at'    => now()->addMinutes(5),
            'last_sent_at'  => now(),
            'consumed_at'   => null,
            'attempt_count' => 0,
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/identity-core/identity/auth/otp/verify', [
                'mobile' => $this->mobile,
                'code'   => $plain,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.must_set_password', true)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'user' => ['user_id', 'mobile'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.access_token'));
    }

    #[Test]
    public function set_password_with_valid_policy_clears_flag_and_forces_relogin(): void
    {
        $token = $this->user->createToken('set_password', ['set-password'])->plainTextToken;

        $response = $this->withHeaders([
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/identity-core/identity/auth/set-password', [
            'password'              => 'Secure9!',
            'password_confirmation' => 'Secure9!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.must_relogin', true);

        $credential = UserCredential::where('user_id', $this->user->user_id)->first();
        $this->assertFalse((bool) $credential->must_set_password);
        $this->assertNotNull($credential->password_hash);
        $this->assertTrue(Hash::check('Secure9!', $credential->password_hash));

        // Tokens revoked
        $this->assertSame(0, $this->user->tokens()->count());
    }

    #[Test]
    public function set_password_rejects_persian_and_short_and_personal(): void
    {
        $token = $this->user->createToken('set_password', ['set-password'])->plainTextToken;
        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];

        // Too short
        $this->withHeaders($headers)
            ->postJson('/api/v1/identity-core/identity/auth/set-password', [
                'password'              => 'Ab1!',
                'password_confirmation' => 'Ab1!',
            ])
            ->assertStatus(422);

        // Persian
        $this->withHeaders($headers)
            ->postJson('/api/v1/identity-core/identity/auth/set-password', [
                'password'              => 'سلامAb1!',
                'password_confirmation' => 'سلامAb1!',
            ])
            ->assertStatus(422);

        // Personal (first name)
        $this->withHeaders($headers)
            ->postJson('/api/v1/identity-core/identity/auth/set-password', [
                'password'              => 'Ali1234!',
                'password_confirmation' => 'Ali1234!',
            ])
            ->assertStatus(422);

        // Missing uppercase
        $this->withHeaders($headers)
            ->postJson('/api/v1/identity-core/identity/auth/set-password', [
                'password'              => 'secure9!',
                'password_confirmation' => 'secure9!',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function after_set_password_user_can_login_with_password(): void
    {
        $credential = UserCredential::where('user_id', $this->user->user_id)->first();
        $credential->password_hash = Hash::make('Secure9!');
        $credential->must_set_password = false;
        $credential->authentication_type = 1;
        $credential->save();

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/identity-core/identity/auth/login', [
                'identifier' => $this->mobile,
                'password'   => 'Secure9!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.must_set_password', false)
            ->assertJsonPath('data.requires_tenant_selection', false)
            ->assertJsonStructure(['data' => ['access_token', 'active_tenant_id']]);
    }

    #[Test]
    public function change_password_requires_current_and_policy(): void
    {
        $credential = UserCredential::where('user_id', $this->user->user_id)->first();
        $credential->password_hash = Hash::make('OldPass1!');
        $credential->must_set_password = false;
        $credential->save();

        $token = $this->user->createToken(
            'tenant_session',
            ['*', 'tenant:'.$this->tenant->tenant_id]
        )->plainTextToken;

        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
        ];

        // Wrong current
        $this->withHeaders($headers)
            ->postJson('/api/v1/identity-core/identity/auth/change-password', [
                'current_password'      => 'Wrong1!',
                'password'              => 'NewPass2!',
                'password_confirmation' => 'NewPass2!',
            ])
            ->assertStatus(401);

        // Valid
        $this->withHeaders($headers)
            ->postJson('/api/v1/identity-core/identity/auth/change-password', [
                'current_password'      => 'OldPass1!',
                'password'              => 'NewPass2!',
                'password_confirmation' => 'NewPass2!',
            ])
            ->assertStatus(200);

        $credential->refresh();
        $this->assertTrue(Hash::check('NewPass2!', $credential->password_hash));
    }
}
