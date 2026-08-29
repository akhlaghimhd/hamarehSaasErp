<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\UserCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * F5: Login / Logout emit versioned events to event_outbox.
 */
class AuthOutboxEventsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $plainPassword = 'SecurePassword123!';
    protected string $userEmail = 'auth.outbox@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'AUTH_OUTBOX',
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
    }

    /** @test */
    public function successful_login_writes_logged_in_outbox_event(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/login', [
            'email'     => $this->userEmail,
            'password'  => $this->plainPassword,
            'tenant_id' => $this->tenant->tenant_id,
        ]);

        $response->assertStatus(200);

        $event = DB::table('event_outbox')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('event_type', 'identity.user.logged_in.v1')
            ->where('aggregate_id', $this->user->user_id)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('users', $event->aggregate_type);
        $this->assertSame(1, (int) $event->status);

        $payload = json_decode($event->payload, true);
        $this->assertSame($this->user->user_id, $payload['user_id']);
        $this->assertSame($this->tenant->tenant_id, $payload['tenant_id']);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('access_token', $payload);
    }

    /** @test */
    public function logout_writes_logged_out_outbox_event_and_invalidates_token(): void
    {
        $login = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/login', [
            'email'     => $this->userEmail,
            'password'  => $this->plainPassword,
            'tenant_id' => $this->tenant->tenant_id,
        ]);

        $token = $login->json('data.access_token');
        $this->assertNotEmpty($token);

        $this->assertGreaterThan(
            0,
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $this->user->user_id)
                ->count()
        );

        $logout = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/logout');

        $logout->assertStatus(200);

        $event = DB::table('event_outbox')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('event_type', 'identity.user.logged_out.v1')
            ->where('aggregate_id', $this->user->user_id)
            ->first();

        $this->assertNotNull($event);
        $payload = json_decode($event->payload, true);
        $this->assertSame($this->user->user_id, $payload['user_id']);

        $this->assertSame(
            0,
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $this->user->user_id)
                ->count(),
            'All personal access tokens for the user must be revoked after logout'
        );

        $reuse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/identity-core/identity/auth/logout');

        $reuse->assertStatus(401);
    }
}
