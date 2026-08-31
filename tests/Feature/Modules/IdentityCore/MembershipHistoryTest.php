<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\IdentityCore\Models\TenantMembershipHistory;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class MembershipHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $memberUser;
    protected TenantUser $memberMembership;
    protected string $adminToken;
    protected string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'MHIST_TEST',
            'status'      => 1,
        ]);

        $this->adminUser = User::factory()->create(['status' => 1]);
        $this->memberUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->adminUser->user_id,
            'status'    => 1,
        ]);

        $this->memberMembership = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->memberUser->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'membership-history-viewer',
            'name'      => 'Membership History Viewer',
            'status'    => 1,
        ]);

        foreach ([
            'identity.membership_history.view',
            'identity.user.view',
            'identity.user.update',
            'identity.user.delete',
        ] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Identity',
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
            'user_id'             => $this->adminUser->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->adminToken = $this->adminUser->createToken(
            'mhist-admin',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->memberToken = $this->memberUser->createToken(
            'mhist-member',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
    }

    protected function headers(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function authorized_user_can_list_membership_history_for_tenant(): void
    {
        TenantMembershipHistory::create([
            'history_id'      => (string) Str::uuid(),
            'tenant_id'       => $this->tenant->tenant_id,
            'tenant_user_id'  => $this->memberMembership->tenant_user_id,
            'previous_status' => null,
            'new_status'      => 1,
            'reason_code'     => 'JOIN',
            'description'     => 'Seed join',
            'effective_date'  => now(),
            'row_version'     => 1,
        ]);

        $response = $this->withHeaders($this->headers($this->adminToken))
            ->getJson('/api/identity-core/identity/membership-histories');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertNotEmpty($response->json('data'));
    }

    #[Test]
    public function authorized_user_can_list_history_by_tenant_user(): void
    {
        TenantMembershipHistory::create([
            'history_id'      => (string) Str::uuid(),
            'tenant_id'       => $this->tenant->tenant_id,
            'tenant_user_id'  => $this->memberMembership->tenant_user_id,
            'previous_status' => 1,
            'new_status'      => 2,
            'reason_code'     => 'STATUS_CHANGE',
            'description'     => 'Suspended',
            'effective_date'  => now(),
            'row_version'     => 1,
        ]);

        $response = $this->withHeaders($this->headers($this->adminToken))
            ->getJson(
                '/api/identity-core/identity/membership-histories/user/'
                . $this->memberMembership->tenant_user_id
            );

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.tenant_user_id', $this->memberMembership->tenant_user_id)
            ->assertJsonPath('data.0.new_status', 2);
    }

    #[Test]
    public function updating_membership_status_records_history(): void
    {
        $response = $this->withHeaders($this->headers($this->adminToken))
            ->putJson(
                '/api/identity-core/identity/users/' . $this->memberMembership->tenant_user_id,
                ['status' => 2]
            );

        $response->assertStatus(200);

        $this->assertDatabaseHas('tenant_membership_histories', [
            'tenant_id'       => $this->tenant->tenant_id,
            'tenant_user_id'  => $this->memberMembership->tenant_user_id,
            'previous_status' => 1,
            'new_status'      => 2,
            'reason_code'     => 'STATUS_CHANGE',
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => 'identity.membership_history.recorded.v1',
            'status'     => 1,
        ]);
    }

    #[Test]
    public function soft_delete_membership_records_history(): void
    {
        $response = $this->withHeaders($this->headers($this->adminToken))
            ->deleteJson(
                '/api/identity-core/identity/users/' . $this->memberMembership->tenant_user_id
            );

        $response->assertStatus(200);

        $this->assertDatabaseHas('tenant_membership_histories', [
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $this->memberMembership->tenant_user_id,
            'new_status'     => 0,
            'reason_code'    => 'SOFT_DELETE',
        ]);
    }

    #[Test]
    public function unauthorized_member_cannot_list_membership_history(): void
    {
        $this->withHeaders($this->headers($this->memberToken))
            ->getJson('/api/identity-core/identity/membership-histories')
            ->assertStatus(403);
    }

    #[Test]
    public function cannot_list_history_for_membership_outside_tenant(): void
    {
        $otherTenant = Tenant::factory()->create([
            'tenant_code' => 'MHIST_OTHER',
            'status'      => 1,
        ]);

        $outsider = User::factory()->create(['status' => 1]);
        $foreignMembership = TenantUser::factory()->create([
            'tenant_id' => $otherTenant->tenant_id,
            'user_id'   => $outsider->user_id,
            'status'    => 1,
        ]);

        $this->withHeaders($this->headers($this->adminToken))
            ->getJson(
                '/api/identity-core/identity/membership-histories/user/'
                . $foreignMembership->tenant_user_id
            )
            ->assertStatus(404);
    }
}
