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
use App\Modules\PartnerLayer\Models\PartnerAgreement;
use App\Modules\PartnerLayer\Models\PartnerCommission;
use App\Modules\PartnerLayer\Models\PartnerPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * P3-T3 — Isolation: no data bleeding between partners / tenants.
 */
class PartnerLayerIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $tokenA;
    protected Partner $partnerA;
    protected Partner $partnerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'ISO_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'ISO_B', 'status' => 1]);

        $this->userA = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'ISO_ADMIN',
            'name'      => 'Isolation Admin',
        ]);

        $codes = [
            'partner.partner.view',
            'partner.partner.create',
            'partner.agreement.view',
            'partner.commission.view',
            'partner.payout.view',
        ];

        foreach ($codes as $code) {
            $permission = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenantA->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'PartnerLayer',
                'status'               => 1,
            ]);

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenantA->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $permission->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenantA->tenant_id,
            'user_id'             => $this->userA->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->tokenA = $this->userA->createToken(
            'iso-test',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $this->partnerA = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenantA->tenant_id,
            'code'       => 'ISO-A-P',
            'name'       => 'Partner Tenant A',
            'status'     => 1,
        ]);

        $this->partnerB = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenantB->tenant_id,
            'code'       => 'ISO-B-P',
            'name'       => 'Partner Tenant B',
            'status'     => 1,
        ]);
    }

    protected function authHeadersA(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->tokenA,
            'X-Tenant-ID'   => $this->tenantA->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function tenant_a_list_does_not_include_tenant_b_partners(): void
    {
        $response = $this->withHeaders($this->authHeadersA())
            ->getJson('/api/partner-layer/partners');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('partner_id')->all();

        $this->assertContains($this->partnerA->partner_id, $ids);
        $this->assertNotContains($this->partnerB->partner_id, $ids);
    }

    #[Test]
    public function tenant_a_cannot_show_tenant_b_partner_by_id(): void
    {
        $response = $this->withHeaders($this->authHeadersA())
            ->getJson('/api/partner-layer/partners/' . $this->partnerB->partner_id);

        $response->assertStatus(404);
    }

    #[Test]
    public function tenant_a_cannot_list_agreements_of_tenant_b_partner(): void
    {
        PartnerAgreement::create([
            'agreement_id'     => (string) Str::uuid(),
            'partner_id'       => $this->partnerB->partner_id,
            'agreement_number' => 'AGR-B-ISO',
            'agreement_type'   => 1,
            'start_date'       => now(),
            'status'           => 1,
        ]);

        $response = $this->withHeaders($this->authHeadersA())
            ->getJson('/api/partner-layer/partner-agreements?partner_id=' . $this->partnerB->partner_id);

        // Service rejects inaccessible partner context
        $this->assertTrue(
            in_array($response->status(), [200, 422], true),
            'Expected 200 (empty) or 422 (denied), got ' . $response->status()
        );

        if ($response->status() === 200) {
            $numbers = collect($response->json('data'))->pluck('agreement_number')->all();
            $this->assertNotContains('AGR-B-ISO', $numbers);
        }
    }

    #[Test]
    public function tenant_a_cannot_list_commissions_of_tenant_b_partner(): void
    {
        PartnerCommission::create([
            'commission_id'             => (string) Str::uuid(),
            'partner_id'                => $this->partnerB->partner_id,
            'tenant_id'                 => $this->tenantB->tenant_id,
            'base_amount'               => '999.0000',
            'commission_type_snapshot'  => 1,
            'commission_value_snapshot' => '10.0000',
            'commission_amount'         => '99.9000',
            'currency_id'               => (string) Str::uuid(),
            'exchange_rate'             => '1.00000000',
            'status'                    => 1,
            'calculated_at'             => now(),
        ]);

        $response = $this->withHeaders($this->authHeadersA())
            ->getJson('/api/partner-layer/partner-commissions?partner_id=' . $this->partnerB->partner_id);

        $this->assertTrue(
            in_array($response->status(), [200, 422], true)
        );

        if ($response->status() === 200) {
            $this->assertEmpty($response->json('data'));
        }
    }

    #[Test]
    public function tenant_a_cannot_show_payout_of_tenant_b_partner(): void
    {
        $payout = PartnerPayout::create([
            'payout_id'     => (string) Str::uuid(),
            'partner_id'    => $this->partnerB->partner_id,
            'payout_number' => 'PO-B-ISO',
            'total_amount'  => '500.0000',
            'currency_id'   => (string) Str::uuid(),
            'status'        => 1,
        ]);

        $response = $this->withHeaders($this->authHeadersA())
            ->getJson('/api/partner-layer/partner-payouts/' . $payout->payout_id);

        $response->assertStatus(404);
    }

    #[Test]
    public function soft_deleted_partner_is_not_listed_for_own_tenant(): void
    {
        $deleted = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenantA->tenant_id,
            'code'       => 'ISO-A-DEL',
            'name'       => 'Deleted Partner',
            'status'     => 1,
        ]);
        $deleted->delete();

        $response = $this->withHeaders($this->authHeadersA())
            ->getJson('/api/partner-layer/partners');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('partner_id')->all();
        $this->assertNotContains($deleted->partner_id, $ids);
    }
}
