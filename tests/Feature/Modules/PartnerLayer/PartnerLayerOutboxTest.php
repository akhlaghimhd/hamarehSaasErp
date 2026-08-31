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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * P3-X1 — PartnerLayer writes versioned events to event_outbox.
 */
class PartnerLayerOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'P3_OX',
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
            'code'      => 'OX_MGR',
            'name'      => 'Outbox Manager',
        ]);

        foreach ([
            'partner.partner.view',
            'partner.partner.create',
            'partner.partner.delete',
            'partner.agreement.view',
            'partner.agreement.create',
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
            'ox-test',
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
    public function creating_partner_writes_partner_created_outbox_event(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partners', [
                'code' => 'OX-P1',
                'name' => 'Outbox Partner',
            ]);

        $response->assertStatus(201);

        $partnerId = $response->json('data.partner_id');

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'      => $this->tenant->tenant_id,
            'aggregate_type' => 'partners',
            'aggregate_id'   => $partnerId,
            'event_type'     => 'PartnerLayer.PartnerCreated.v1',
            'status'         => 1,
        ]);
    }

    #[Test]
    public function deleting_partner_writes_partner_deleted_outbox_event(): void
    {
        $partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'OX-DEL',
            'name'       => 'To Delete',
            'status'     => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/partner-layer/partners/' . $partner->partner_id);

        $response->assertStatus(200);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'      => $this->tenant->tenant_id,
            'aggregate_type' => 'partners',
            'aggregate_id'   => $partner->partner_id,
            'event_type'     => 'PartnerLayer.PartnerDeleted.v1',
            'status'         => 1,
        ]);
    }

    #[Test]
    public function creating_agreement_writes_agreement_created_outbox_event(): void
    {
        $partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'OX-AGR-P',
            'name'       => 'Agreement Partner',
            'status'     => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-agreements', [
                'partner_id'       => $partner->partner_id,
                'agreement_number' => 'OX-AGR-001',
                'agreement_type'   => 1,
                'start_date'       => now()->toIso8601String(),
                'payment_cycle'    => 30,
                'status'           => 1,
            ]);

        $this->assertSame(
            201,
            $response->status(),
            'Agreement create failed: ' . json_encode($response->json())
        );

        $agreementId = $response->json('data.agreement_id');

        $row = DB::table('event_outbox')
            ->where('event_type', 'PartnerLayer.PartnerAgreementCreated.v1')
            ->where('aggregate_id', $agreementId)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($this->tenant->tenant_id, $row->tenant_id);
        $this->assertSame(1, (int) $row->status);
    }
}
