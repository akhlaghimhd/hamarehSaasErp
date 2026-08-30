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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * P3-T2 — PartnerAgreement CRUD feature tests.
 */
class PartnerAgreementCrudTest extends TestCase
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
            'tenant_code' => 'P3_AGR',
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
            'code'      => 'AGREEMENT_MANAGER',
            'name'      => 'Agreement Manager',
        ]);

        foreach ([
            'partner.agreement.view',
            'partner.agreement.create',
            'partner.agreement.update',
            'partner.agreement.delete',
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
            'agreement-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'AGR-P',
            'name'       => 'Partner For Agreement',
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
    public function authorized_user_can_create_agreement(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-agreements', [
                'partner_id'       => $this->partner->partner_id,
                'agreement_number' => 'AGR-2026-001',
                'agreement_type'   => 1,
                'payment_cycle'    => 30,
                'status'           => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.agreement_number', 'AGR-2026-001');

        $this->assertDatabaseHas('partner_agreements', [
            'partner_id'       => $this->partner->partner_id,
            'agreement_number' => 'AGR-2026-001',
        ]);
    }

    #[Test]
    public function duplicate_agreement_number_is_rejected(): void
    {
        PartnerAgreement::create([
            'agreement_id'     => (string) Str::uuid(),
            'partner_id'       => $this->partner->partner_id,
            'agreement_number' => 'DUP-001',
            'agreement_type'   => 1,
            'status'           => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-agreements', [
                'partner_id'       => $this->partner->partner_id,
                'agreement_number' => 'DUP-001',
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function authorized_user_can_list_agreements(): void
    {
        PartnerAgreement::create([
            'agreement_id'     => (string) Str::uuid(),
            'partner_id'       => $this->partner->partner_id,
            'agreement_number' => 'LIST-001',
            'agreement_type'   => 1,
            'status'           => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/partner-layer/partner-agreements?partner_id=' . $this->partner->partner_id);

        $response->assertStatus(200);
        $numbers = collect($response->json('data'))->pluck('agreement_number')->all();
        $this->assertContains('LIST-001', $numbers);
    }

    #[Test]
    public function authorized_user_can_update_agreement(): void
    {
        $agreement = PartnerAgreement::create([
            'agreement_id'     => (string) Str::uuid(),
            'partner_id'       => $this->partner->partner_id,
            'agreement_number' => 'UPD-001',
            'agreement_type'   => 1,
            'description'      => 'Before',
            'status'           => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/partner-layer/partner-agreements/' . $agreement->agreement_id, [
                'description' => 'After',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.description', 'After');
    }

    #[Test]
    public function authorized_user_can_soft_delete_agreement(): void
    {
        $agreement = PartnerAgreement::create([
            'agreement_id'     => (string) Str::uuid(),
            'partner_id'       => $this->partner->partner_id,
            'agreement_number' => 'DEL-001',
            'agreement_type'   => 1,
            'status'           => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/partner-layer/partner-agreements/' . $agreement->agreement_id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('partner_agreements', [
            'agreement_id' => $agreement->agreement_id,
        ]);
    }
}
