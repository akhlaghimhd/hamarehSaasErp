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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * P3-T2 — PartnerCommissionRule CRUD feature tests.
 */
class PartnerCommissionRuleCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;
    protected Partner $partner;
    protected PartnerAgreement $agreement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'P3_CR',
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
            'code'      => 'COMMISSION_RULE_MANAGER',
            'name'      => 'Commission Rule Manager',
        ]);

        foreach ([
            'partner.commission_rule.view',
            'partner.commission_rule.create',
            'partner.commission_rule.update',
            'partner.commission_rule.delete',
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
            'commission-rule-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'CR-P',
            'name'       => 'Partner For Commission Rule',
            'status'     => 1,
        ]);

        $this->agreement = PartnerAgreement::create([
            'agreement_id'     => (string) Str::uuid(),
            'partner_id'       => $this->partner->partner_id,
            'agreement_number' => 'AGR-CR-001',
            'agreement_type'   => 1,
            'start_date'       => now(),
            'status'           => 1,
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
    public function authorized_user_can_create_commission_rule(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-commission-rules', [
                'agreement_id'      => $this->agreement->agreement_id,
                'revenue_type'      => 1,
                'commission_type'   => 1,
                'commission_value'  => '10.5000',
                'calculation_basis' => 1,
                'status'            => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('partner_commission_rules', [
            'agreement_id'     => $this->agreement->agreement_id,
            'revenue_type'     => 1,
            'commission_type'  => 1,
        ]);
    }

    #[Test]
    public function invalid_min_max_amount_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-commission-rules', [
                'agreement_id'     => $this->agreement->agreement_id,
                'revenue_type'     => 1,
                'commission_type'  => 1,
                'commission_value' => '5',
                'minimum_amount'   => '100',
                'maximum_amount'   => '50',
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function authorized_user_can_list_commission_rules(): void
    {
        PartnerCommissionRule::create([
            'commission_rule_id' => (string) Str::uuid(),
            'agreement_id'       => $this->agreement->agreement_id,
            'revenue_type'       => 1,
            'commission_type'    => 1,
            'commission_value'   => '12.0000',
            'calculation_basis'  => 1,
            'effective_from'     => now(),
            'status'             => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/partner-layer/partner-commission-rules?agreement_id=' . $this->agreement->agreement_id);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    #[Test]
    public function authorized_user_can_update_commission_rule(): void
    {
        $rule = PartnerCommissionRule::create([
            'commission_rule_id' => (string) Str::uuid(),
            'agreement_id'       => $this->agreement->agreement_id,
            'revenue_type'       => 1,
            'commission_type'    => 1,
            'commission_value'   => '10.0000',
            'calculation_basis'  => 1,
            'effective_from'     => now(),
            'status'             => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/partner-layer/partner-commission-rules/' . $rule->commission_rule_id, [
                'commission_value' => '15.2500',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('15.2500', (string) $response->json('data.commission_value'));
    }

    #[Test]
    public function authorized_user_can_soft_delete_commission_rule(): void
    {
        $rule = PartnerCommissionRule::create([
            'commission_rule_id' => (string) Str::uuid(),
            'agreement_id'       => $this->agreement->agreement_id,
            'revenue_type'       => 1,
            'commission_type'    => 1,
            'commission_value'   => '8.0000',
            'calculation_basis'  => 1,
            'effective_from'     => now(),
            'status'             => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/partner-layer/partner-commission-rules/' . $rule->commission_rule_id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('partner_commission_rules', [
            'commission_rule_id' => $rule->commission_rule_id,
        ]);
    }
}
