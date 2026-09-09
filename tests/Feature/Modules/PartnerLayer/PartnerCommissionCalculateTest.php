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
use App\Modules\PartnerLayer\Models\PartnerCommissionRule;
use App\Modules\PartnerLayer\Services\PartnerCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L3-P3-05 — Commission calculation from active rule (percentage + fixed + min/max).
 */
class PartnerCommissionCalculateTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;
    protected Partner $partner;
    protected PartnerCommissionRule $percentRule;
    protected PartnerCommissionRule $fixedRule;
    protected string $currencyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'P3_CALC', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);
        $this->currencyId = (string) Str::uuid();

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'CALC_MGR',
            'name'      => 'Commission Calc Manager',
        ]);

        foreach (['partner.commission.view', 'partner.commission.create'] as $code) {
            $perm = TenantPermission::create([
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
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->user->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->token = $this->user->createToken(
            'calc-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->partner = Partner::create([
            'partner_id'         => (string) Str::uuid(),
            'tenant_id'          => $this->tenant->tenant_id,
            'code'               => 'CALC-P',
            'name'               => 'Calc Partner',
            'commission_enabled' => true,
            'status'             => 1,
        ]);

        $agreement = PartnerAgreement::create([
            'agreement_id'     => (string) Str::uuid(),
            'partner_id'       => $this->partner->partner_id,
            'agreement_number' => 'AGR-CALC-1',
            'agreement_type'   => 1,
            'start_date'       => now()->subMonth(),
            'status'           => 1,
        ]);

        $this->percentRule = PartnerCommissionRule::create([
            'commission_rule_id' => (string) Str::uuid(),
            'agreement_id'       => $agreement->agreement_id,
            'revenue_type'       => 1,
            'commission_type'    => 1,
            'commission_value'   => 10.0000,
            'calculation_basis'  => 1,
            'minimum_amount'     => 5.0000,
            'maximum_amount'     => 100.0000,
            'effective_from'     => now()->subDay(),
            'status'             => 1,
        ]);

        $this->fixedRule = PartnerCommissionRule::create([
            'commission_rule_id' => (string) Str::uuid(),
            'agreement_id'       => $agreement->agreement_id,
            'revenue_type'       => 1,
            'commission_type'    => 2,
            'commission_value'   => 25.0000,
            'calculation_basis'  => 1,
            'effective_from'     => now()->subDay(),
            'status'             => 1,
        ]);
    }

    #[Test]
    public function it_calculates_percentage_commission_with_min_max(): void
    {
        $service = app(PartnerCommissionService::class);

        $c1 = $service->calculateFromRule(
            $this->partner->partner_id,
            $this->tenant->tenant_id,
            $this->percentRule->commission_rule_id,
            '1000.0000',
            $this->currencyId
        );
        $this->assertEquals('100.0000', $c1->commission_amount);

        $c2 = $service->calculateFromRule(
            $this->partner->partner_id,
            $this->tenant->tenant_id,
            $this->percentRule->commission_rule_id,
            '20.0000',
            $this->currencyId
        );
        $this->assertEquals('5.0000', $c2->commission_amount);

        $c3 = $service->calculateFromRule(
            $this->partner->partner_id,
            $this->tenant->tenant_id,
            $this->percentRule->commission_rule_id,
            '2000.0000',
            $this->currencyId
        );
        $this->assertEquals('100.0000', $c3->commission_amount);
    }

    #[Test]
    public function it_calculates_fixed_commission_via_api(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
        ])->postJson('/api/partner-layer/partner-commissions/calculate', [
            'partner_id'         => $this->partner->partner_id,
            'tenant_id'          => $this->tenant->tenant_id,
            'commission_rule_id' => $this->fixedRule->commission_rule_id,
            'base_amount'        => 500,
            'currency_id'        => $this->currencyId,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.commission_amount', '25.0000')
            ->assertJsonPath('data.commission_type_snapshot', 2);
    }
}
