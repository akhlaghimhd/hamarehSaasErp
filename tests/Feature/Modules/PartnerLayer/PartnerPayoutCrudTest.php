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
use App\Modules\PartnerLayer\Models\PartnerPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * P3-T2 — PartnerPayout CRUD feature tests.
 */
class PartnerPayoutCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;
    protected Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'P3_PAY',
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
            'code'      => 'PAYOUT_MANAGER',
            'name'      => 'Payout Manager',
        ]);

        foreach ([
            'partner.payout.view',
            'partner.payout.create',
            'partner.payout.update',
            'partner.payout.delete',
        ] as $code) {
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
            'payout-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'PAY-P',
            'name'       => 'Partner For Payout',
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
    public function authorized_user_can_create_payout(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-payouts', [
                'partner_id'    => $this->partner->partner_id,
                'payout_number' => 'PO-2026-001',
                'total_amount'  => '1500.0000',
                'currency_id'   => (string) Str::uuid(),
                'status'        => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.payout_number', 'PO-2026-001');

        $this->assertDatabaseHas('partner_payouts', [
            'partner_id'    => $this->partner->partner_id,
            'payout_number' => 'PO-2026-001',
        ]);
    }

    #[Test]
    public function duplicate_payout_number_is_rejected(): void
    {
        PartnerPayout::create([
            'payout_id'     => (string) Str::uuid(),
            'partner_id'    => $this->partner->partner_id,
            'payout_number' => 'DUP-PO-01',
            'total_amount'  => '100.0000',
            'currency_id'   => (string) Str::uuid(),
            'status'        => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-payouts', [
                'partner_id'    => $this->partner->partner_id,
                'payout_number' => 'DUP-PO-01',
                'total_amount'  => '200.0000',
                'currency_id'   => (string) Str::uuid(),
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function authorized_user_can_list_payouts(): void
    {
        PartnerPayout::create([
            'payout_id'     => (string) Str::uuid(),
            'partner_id'    => $this->partner->partner_id,
            'payout_number' => 'LIST-PO-01',
            'total_amount'  => '300.0000',
            'currency_id'   => (string) Str::uuid(),
            'status'        => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/partner-layer/partner-payouts?partner_id=' . $this->partner->partner_id);

        $response->assertStatus(200);
        $numbers = collect($response->json('data'))->pluck('payout_number')->all();
        $this->assertContains('LIST-PO-01', $numbers);
    }

    #[Test]
    public function authorized_user_can_update_payout(): void
    {
        $payout = PartnerPayout::create([
            'payout_id'     => (string) Str::uuid(),
            'partner_id'    => $this->partner->partner_id,
            'payout_number' => 'UPD-PO-01',
            'total_amount'  => '400.0000',
            'currency_id'   => (string) Str::uuid(),
            'status'        => 1,
            'description'   => 'Before',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/partner-layer/partner-payouts/' . $payout->payout_id, [
                'status'      => 2,
                'description' => 'After',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 2)
            ->assertJsonPath('data.description', 'After');
    }

    #[Test]
    public function authorized_user_can_soft_delete_payout(): void
    {
        $payout = PartnerPayout::create([
            'payout_id'     => (string) Str::uuid(),
            'partner_id'    => $this->partner->partner_id,
            'payout_number' => 'DEL-PO-01',
            'total_amount'  => '50.0000',
            'currency_id'   => (string) Str::uuid(),
            'status'        => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/partner-layer/partner-payouts/' . $payout->payout_id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('partner_payouts', [
            'payout_id' => $payout->payout_id,
        ]);
    }
}
