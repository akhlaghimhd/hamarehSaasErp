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
use App\Modules\PartnerLayer\Models\PartnerActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L3-P3-04 — PartnerActivityLog create/list feature tests (append-only).
 */
class PartnerActivityLogCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;
    protected Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'P3_LOG', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'LOG_MGR',
            'name'      => 'Activity Log Manager',
        ]);

        foreach (['partner.activity_log.view', 'partner.activity_log.create'] as $code) {
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
            'log-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'LOG-P',
            'name'       => 'Partner Activity Owner',
            'status'     => 1,
        ]);
    }

    #[Test]
    public function it_creates_and_lists_activity_logs(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
        ])->postJson('/api/partner-layer/partner-activity-logs', [
            'partner_id'  => $this->partner->partner_id,
            'user_id'     => $this->user->user_id,
            'action_type' => 'PROFILE_UPDATE',
            'description' => 'Partner profile updated',
            'ip_address'  => '127.0.0.1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('partner_activity_logs', [
            'partner_id'  => $this->partner->partner_id,
            'action_type' => 'PROFILE_UPDATE',
            'ip_address'  => '127.0.0.1',
        ]);

        $list = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
        ])->getJson('/api/partner-layer/partner-activity-logs?partner_id=' . $this->partner->partner_id);

        $list->assertStatus(200)->assertJsonPath('status', 'success');
    }

    #[Test]
    public function it_shows_a_single_activity_log(): void
    {
        $log = PartnerActivityLog::create([
            'partner_log_id' => (string) Str::uuid(),
            'partner_id'     => $this->partner->partner_id,
            'user_id'        => $this->user->user_id,
            'action_type'    => 'WITHDRAWAL_REQUEST',
            'description'    => 'Payout requested',
            'ip_address'     => '10.0.0.1',
            'created_at'     => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
        ])->getJson('/api/partner-layer/partner-activity-logs/' . $log->partner_log_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }
}
