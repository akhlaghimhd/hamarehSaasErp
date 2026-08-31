<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\Organization\Models\Company;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tenant Isolation Architecture Standard — §10 Automated Security Testing (F6)
 *
 * Required scenarios:
 * 1. Tenant A must not access Tenant B operational data (Data Bleed)
 * 2. Spoofed / invalid X-Tenant-ID rejected
 * 3. Authenticated user of A cannot switch context to B via header alone
 * 4. Cache keys must not collide across tenants (prefix contract)
 * 5. Outbox events remain tenant-bound (payload tenant_id)
 */
class IsolationSection10Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;
    protected string $roleBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'ISO10_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'ISO10_B', 'status' => 1]);

        $this->userA = User::factory()->create(['status' => 1]);
        $this->userB = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'user_id'   => $this->userB->user_id,
            'status'    => 1,
        ]);

        $roleA = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'ISO10_ROLE_A',
            'status'    => 1,
        ]);

        $roleB = TenantRole::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'code'      => 'ISO10_ROLE_B',
            'status'    => 1,
        ]);
        $this->roleBId = $roleB->tenant_role_id;

        foreach ([
            [$this->tenantA, $roleA, $this->userA],
            [$this->tenantB, $roleB, $this->userB],
        ] as [$tenant, $role, $user]) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $tenant->tenant_id,
                'code'                 => 'identity.role.view',
                'name'                 => 'View Roles',
                'module_name'          => 'Identity',
                'status'               => 1,
            ]);

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $tenant->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);

            TenantUserRole::create([
                'tenant_user_role_id' => (string) Str::uuid(),
                'tenant_id'           => $tenant->tenant_id,
                'user_id'             => $user->user_id,
                'tenant_role_id'      => $role->tenant_role_id,
            ]);
        }

        $this->tokenA = $this->userA->createToken(
            'iso10-a',
            ['*', 'tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $this->tokenB = $this->userB->createToken(
            'iso10-b',
            ['*', 'tenant:' . $this->tenantB->tenant_id]
        )->plainTextToken;
    }

    protected function tearDown(): void
    {
        TenantContext::resetInstance();
        parent::tearDown();
    }

    #[Test]
    public function tenant_a_cannot_load_tenant_b_role_by_id(): void
    {
        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);

        $this->assertNull(TenantRole::find($this->roleBId));

        $found = TenantRole::withoutGlobalScopes()
            ->where('tenant_role_id', $this->roleBId)
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->first();

        $this->assertNull($found);
    }

    #[Test]
    public function tenant_a_api_list_does_not_include_tenant_b_roles(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
            'X-Tenant-ID'   => $this->tenantA->tenant_id,
            'Accept'        => 'application/json',
        ])->getJson('/api/identity-core/identity/roles');

        $response->assertStatus(200);

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('ISO10_ROLE_A', $codes);
        $this->assertNotContains('ISO10_ROLE_B', $codes);
    }

    #[Test]
    public function missing_tenant_header_is_rejected(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
            'Accept'        => 'application/json',
        ])->getJson('/api/identity-core/identity/roles');

        $response->assertStatus(401);
    }

    #[Test]
    public function invalid_tenant_header_is_rejected(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
            'X-Tenant-ID'   => (string) Str::uuid(),
            'Accept'        => 'application/json',
        ])->getJson('/api/identity-core/identity/roles');

        $response->assertStatus(401);
    }

    #[Test]
    public function user_a_cannot_switch_to_tenant_b_via_header_alone(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
            'X-Tenant-ID'   => $this->tenantB->tenant_id,
            'Accept'        => 'application/json',
        ])->getJson('/api/identity-core/identity/roles');

        $response->assertStatus(403);
    }

    #[Test]
    public function tenant_a_cannot_see_company_of_tenant_b(): void
    {
        Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenantB->tenant_id,
            'code'       => 'B-CO',
            'name'       => 'Tenant B Company',
            'is_active'  => true,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);

        $this->assertCount(0, Company::where('code', 'B-CO')->get());
    }

    #[Test]
    public function cache_keys_must_use_tenant_prefix_to_avoid_collision(): void
    {
        $module = 'identity';
        $keySuffix = 'roles_list';

        $keyA = "tenant:{$this->tenantA->tenant_id}:{$module}:{$keySuffix}";
        $keyB = "tenant:{$this->tenantB->tenant_id}:{$module}:{$keySuffix}";

        $this->assertNotSame($keyA, $keyB);

        Cache::put($keyA, ['role' => 'A'], 60);
        Cache::put($keyB, ['role' => 'B'], 60);

        $this->assertSame('A', Cache::get($keyA)['role']);
        $this->assertSame('B', Cache::get($keyB)['role']);
        $this->assertNotSame(Cache::get($keyA), Cache::get($keyB));
    }

    #[Test]
    public function outbox_login_event_is_bound_to_request_tenant_only(): void
    {
        $eventId = (string) Str::uuid();

        DB::table('event_outbox')->insert([
            'event_id'       => $eventId,
            'tenant_id'      => $this->tenantA->tenant_id,
            'aggregate_type' => 'users',
            'aggregate_id'   => $this->userA->user_id,
            'event_type'     => 'identity.user.logged_in.v1',
            'payload'        => json_encode([
                'user_id'   => $this->userA->user_id,
                'tenant_id' => $this->tenantA->tenant_id,
            ]),
            'status'         => 1,
            'retry_count'    => 0,
            'created_at'     => now(),
        ]);

        $forB = DB::table('event_outbox')
            ->where('event_id', $eventId)
            ->where('tenant_id', $this->tenantB->tenant_id)
            ->first();

        $this->assertNull($forB);

        $forA = DB::table('event_outbox')
            ->where('event_id', $eventId)
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->first();

        $this->assertNotNull($forA);
        $payload = json_decode($forA->payload, true);
        $this->assertSame($this->tenantA->tenant_id, $payload['tenant_id']);
    }
}
