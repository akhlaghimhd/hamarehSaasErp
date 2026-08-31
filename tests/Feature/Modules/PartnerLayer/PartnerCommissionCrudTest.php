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
use App\Modules\PartnerLayer\Models\PartnerCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * P3-T2 — PartnerCommission CRUD feature tests.
 */
class PartnerCommissionCrudTest extends TestCase
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
            'tenant_code' => 'P3_COM',
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
            'code'      => 'COMMISSION_MANAGER',
            'name'      => 'Commission Manager',
        ]);

        foreach ([
            'partner.commission.view',
            'partner.commission.create',
            'partner.commission.update',
            'partner.commission.delete',
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
            'commission-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'COM-P',
            'name'       => 'Partner For Commission',
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
    public function authorized_user_can_create_commission(): void
    {
        $currencyId = (string) Str::uuid();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-commissions', [
                'partner_id'                => $this->partner->partner_id,
                'tenant_id'                 => $this->tenant->tenant_id,
                'base_amount'               => '1000.0000',
                'commission_type_snapshot'  => 1,
                'commission_value_snapshot' => '10.0000',
                'commission_amount'         => '100.0000',
                'currency_id'               => $currencyId,
                'exchange_rate'             => '1.00000000',
                'status'                    => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('partner_commissions', [
            'partner_id' => $this->partner->partner_id,
            'tenant_id'  => $this->tenant->tenant_id,
        ]);
    }

    #[Test]
    public function negative_amounts_are_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-commissions', [
                'partner_id'                => $this->partner->partner_id,
                'tenant_id'                 => $this->tenant->tenant_id,
                'base_amount'               => '-10',
                'commission_type_snapshot'  => 1,
                'commission_value_snapshot' => '10',
                'commission_amount'         => '1',
                'currency_id'               => (string) Str::uuid(),
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function authorized_user_can_list_commissions(): void
    {
        PartnerCommission::create([
            'commission_id'              => (string) Str::uuid(),
            'partner_id'                 => $this->partner->partner_id,
            'tenant_id'                  => $this->tenant->tenant_id,
            'base_amount'                => '500.0000',
            'commission_type_snapshot'   => 1,
            'commission_value_snapshot'  => '10.0000',
            'commission_amount'          => '50.0000',
            'currency_id'                => (string) Str::uuid(),
            'exchange_rate'              => '1.00000000',
            'status'                     => 1,
            'calculated_at'              => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/partner-layer/partner-commissions?partner_id=' . $this->partner->partner_id);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    #[Test]
    public function authorized_user_can_update_commission_status(): void
    {
        $commission = PartnerCommission::create([
            'commission_id'              => (string) Str::uuid(),
            'partner_id'                 => $this->partner->partner_id,
            'tenant_id'                  => $this->tenant->tenant_id,
            'base_amount'                => '200.0000',
            'commission_type_snapshot'   => 1,
            'commission_value_snapshot'  => '10.0000',
            'commission_amount'          => '20.0000',
            'currency_id'                => (string) Str::uuid(),
            'exchange_rate'              => '1.00000000',
            'status'                     => 1,
            'calculated_at'              => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/partner-layer/partner-commissions/' . $commission->commission_id, [
                'status'  => 2,
                'paid_at' => now()->toIso8601String(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 2);
    }

    #[Test]
    public function authorized_user_can_soft_delete_commission(): void
    {
        $commission = PartnerCommission::create([
            'commission_id'              => (string) Str::uuid(),
            'partner_id'                 => $this->partner->partner_id,
            'tenant_id'                  => $this->tenant->tenant_id,
            'base_amount'                => '100.0000',
            'commission_type_snapshot'   => 1,
            'commission_value_snapshot'  => '10.0000',
            'commission_amount'          => '10.0000',
            'currency_id'                => (string) Str::uuid(),
            'exchange_rate'              => '1.00000000',
            'status'                     => 1,
            'calculated_at'              => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/partner-layer/partner-commissions/' . $commission->commission_id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('partner_commissions', [
            'commission_id' => $commission->commission_id,
        ]);
    }
}
