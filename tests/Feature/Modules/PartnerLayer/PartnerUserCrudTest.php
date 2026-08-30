<?php

namespace Tests\Feature\Modules\PartnerLayer;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * P3-T1 — PartnerUser CRUD feature tests (logical user_id link).
 */
class PartnerUserCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected User $linkedUser;
    protected string $token;
    protected Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'P3_PU',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create(['status' => 1]);
        $this->linkedUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'PARTNER_USER_MANAGER',
            'name'      => 'Partner User Manager',
        ]);

        $codes = [
            'partner.partner.view',
            'partner.partner.create',
            'partner.partner_user.view',
            'partner.partner_user.create',
            'partner.partner_user.update',
            'partner.partner_user.delete',
        ];

        foreach ($codes as $code) {
            $permission = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'PartnerLayer',
                'status'               => 1,
            ]);

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenant->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $permission->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->user->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->token = $this->user->createToken(
            'partner-user-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'PU-PART',
            'name'       => 'Partner For Users',
            'status'     => 1,
        ]);
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
    public function authorized_user_can_link_user_to_partner(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-users', [
                'partner_id' => $this->partner->partner_id,
                'user_id'    => $this->linkedUser->user_id,
                'is_primary' => true,
                'status'     => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $this->linkedUser->user_id)
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('partner_users', [
            'partner_id' => $this->partner->partner_id,
            'user_id'    => $this->linkedUser->user_id,
            'is_primary' => true,
        ]);
    }

    #[Test]
    public function duplicate_user_link_to_same_partner_is_rejected(): void
    {
        PartnerUser::create([
            'partner_user_id' => (string) Str::uuid(),
            'partner_id'      => $this->partner->partner_id,
            'user_id'         => $this->linkedUser->user_id,
            'is_primary'      => false,
            'status'          => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-users', [
                'partner_id' => $this->partner->partner_id,
                'user_id'    => $this->linkedUser->user_id,
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function authorized_user_can_list_partner_users(): void
    {
        PartnerUser::create([
            'partner_user_id' => (string) Str::uuid(),
            'partner_id'      => $this->partner->partner_id,
            'user_id'         => $this->linkedUser->user_id,
            'is_primary'      => true,
            'status'          => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/partner-layer/partner-users?partner_id=' . $this->partner->partner_id);

        $response->assertStatus(200);
        $userIds = collect($response->json('data'))->pluck('user_id')->all();
        $this->assertContains($this->linkedUser->user_id, $userIds);
    }

    #[Test]
    public function authorized_user_can_update_partner_user(): void
    {
        $pu = PartnerUser::create([
            'partner_user_id' => (string) Str::uuid(),
            'partner_id'      => $this->partner->partner_id,
            'user_id'         => $this->linkedUser->user_id,
            'is_primary'      => false,
            'status'          => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/partner-layer/partner-users/' . $pu->partner_user_id, [
                'is_primary' => true,
                'status'     => 1,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_primary', true);
    }

    #[Test]
    public function authorized_user_can_soft_delete_partner_user(): void
    {
        $pu = PartnerUser::create([
            'partner_user_id' => (string) Str::uuid(),
            'partner_id'      => $this->partner->partner_id,
            'user_id'         => $this->linkedUser->user_id,
            'is_primary'      => false,
            'status'          => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/partner-layer/partner-users/' . $pu->partner_user_id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('partner_users', [
            'partner_user_id' => $pu->partner_user_id,
        ]);
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/partner-layer/partner-users', [
            'partner_id' => $this->partner->partner_id,
            'user_id'    => $this->linkedUser->user_id,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function only_one_primary_user_per_partner(): void
    {
        $otherUser = User::factory()->create(['status' => 1]);

        PartnerUser::create([
            'partner_user_id' => (string) Str::uuid(),
            'partner_id'      => $this->partner->partner_id,
            'user_id'         => $this->linkedUser->user_id,
            'is_primary'      => true,
            'status'          => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-users', [
                'partner_id' => $this->partner->partner_id,
                'user_id'    => $otherUser->user_id,
                'is_primary' => true,
            ]);

        $response->assertStatus(201);

        $primaryCount = PartnerUser::query()
            ->where('partner_id', $this->partner->partner_id)
            ->where('is_primary', true)
            ->count();

        $this->assertSame(1, $primaryCount);
    }
}
