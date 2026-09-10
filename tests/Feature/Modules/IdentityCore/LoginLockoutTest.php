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

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $password = 'SecurePassword123!';
    protected string $email = 'lockout.user@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'LOCKOUT',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create([
            'email'  => $this->email,
            'status' => 1,
        ]);

        UserCredential::create([
            'credential_id'       => (string) Str::uuid(),
            'user_id'             => $this->user->user_id,
            'password_hash'       => Hash::make($this->password),
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
    }

    private function attemptLogin(string $password)
    {
        return $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/v1/identity-core/identity/auth/login', [
            'email'     => $this->email,
            'password'  => $password,
            'tenant_id' => $this->tenant->tenant_id,
        ]);
    }

    #[Test]
    public function failed_login_increments_failed_login_count(): void
    {
        $this->attemptLogin('wrong-password')->assertStatus(401);

        $credential = UserCredential::where('user_id', $this->user->user_id)->first();
        $this->assertSame(1, (int) $credential->failed_login_count);
        $this->assertNull($credential->locked_until);
    }

    #[Test]
    public function account_is_locked_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->attemptLogin('wrong-password')->assertStatus(401);
        }

        $credential = UserCredential::where('user_id', $this->user->user_id)->first();
        $this->assertNotNull($credential->locked_until);
        $this->assertSame(5, (int) $credential->failed_login_count);
    }

    #[Test]
    public function locked_account_rejects_even_with_correct_password(): void
    {
        $credential = UserCredential::where('user_id', $this->user->user_id)->first();
        $credential->failed_login_count = 5;
        $credential->locked_until = now()->addMinutes(15);
        $credential->save();

        $response = $this->attemptLogin($this->password);

        $response->assertStatus(403);
        $this->assertStringContainsString('قفل', $response->json('message') ?? $response->getContent());
    }

    #[Test]
    public function successful_login_clears_failed_count_and_lock(): void
    {
        $credential = UserCredential::where('user_id', $this->user->user_id)->first();
        $credential->failed_login_count = 3;
        $credential->locked_until = null;
        $credential->save();

        $this->attemptLogin($this->password)->assertStatus(200);

        $credential->refresh();
        $this->assertSame(0, (int) $credential->failed_login_count);
        $this->assertNull($credential->locked_until);
    }
}
