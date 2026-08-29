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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * P3-T1 — Partner CRUD feature tests (PartnerLayer).
 */
class PartnerCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'P3_PARTNER',
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
            'code'      => 'PARTNER_MANAGER',
            'name'      => 'Partner Manager',
        ]);

        foreach (['partner.partner.view', 'partner.partner.create', 'partner.partner.update', 'partner.partner.delete'] as $code) {
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
            'partner-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;
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
    public function authorized_user_can_create_partner(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partners', [
                'code' => 'AFF-001',
                'name' => 'Affiliate One',
                'partner_type' => 1,
                'ownership_type' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.code', 'AFF-001');

        $this->assertDatabaseHas('partners', [
            'code'      => 'AFF-001',
            'tenant_id' => $this->tenant->tenant_id,
        ]);
    }

    #[Test]
    public function duplicate_code_in_same_tenant_is_rejected(): void
    {
        Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'DUP-01',
            'name'       => 'Existing',
            'status'     => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partners', [
                'code' => 'DUP-01',
                'name' => 'Another',
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function authorized_user_can_list_partners_for_tenant_only(): void
    {
        Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'LIST-A',
            'name'       => 'Mine',
            'status'     => 1,
        ]);

        $otherTenant = Tenant::factory()->create(['tenant_code' => 'OTHER', 'status' => 1]);

        Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $otherTenant->tenant_id,
            'code'       => 'LIST-B',
            'name'       => 'Other',
            'status'     => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/partner-layer/partners');

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('LIST-A', $codes);
        $this->assertNotContains('LIST-B', $codes);
    }

    #[Test]
    public function authorized_user_can_update_partner(): void
    {
        $partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'UPD-01',
            'name'       => 'Before',
            'status'     => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/partner-layer/partners/' . $partner->partner_id, [
                'code' => 'UPD-01',
                'name' => 'After',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'After');
    }

    #[Test]
    public function authorized_user_can_soft_delete_partner(): void
    {
        $partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'DEL-01',
            'name'       => 'To Delete',
            'status'     => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/partner-layer/partners/' . $partner->partner_id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('partners', [
            'partner_id' => $partner->partner_id,
        ]);
    }
}
