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
use App\Modules\PartnerLayer\Models\PartnerTenantAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * P3-T2 — PartnerTenantAssignment CRUD feature tests.
 */
class PartnerTenantAssignmentCrudTest extends TestCase
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
            'tenant_code' => 'P3_ASN',
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
            'code'      => 'ASSIGNMENT_MANAGER',
            'name'      => 'Assignment Manager',
        ]);

        foreach ([
            'partner.assignment.view',
            'partner.assignment.create',
            'partner.assignment.update',
            'partner.assignment.delete',
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
            'assignment-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'ASN-P',
            'name'       => 'Partner For Assignment',
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
    public function authorized_user_can_create_assignment(): void
    {
        $targetTenantId = (string) Str::uuid();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-tenant-assignments', [
                'partner_id'      => $this->partner->partner_id,
                'tenant_id'       => $targetTenantId,
                'assignment_type' => 1,
                'status'          => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tenant_id', $targetTenantId);

        $this->assertDatabaseHas('partner_tenant_assignments', [
            'partner_id' => $this->partner->partner_id,
            'tenant_id'  => $targetTenantId,
        ]);
    }

    #[Test]
    public function duplicate_active_assignment_is_rejected(): void
    {
        $targetTenantId = (string) Str::uuid();

        PartnerTenantAssignment::create([
            'assignment_id'   => (string) Str::uuid(),
            'partner_id'      => $this->partner->partner_id,
            'tenant_id'       => $targetTenantId,
            'assignment_type' => 1,
            'status'          => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-tenant-assignments', [
                'partner_id' => $this->partner->partner_id,
                'tenant_id'  => $targetTenantId,
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function authorized_user_can_list_assignments(): void
    {
        PartnerTenantAssignment::create([
            'assignment_id'   => (string) Str::uuid(),
            'partner_id'      => $this->partner->partner_id,
            'tenant_id'       => (string) Str::uuid(),
            'assignment_type' => 1,
            'status'          => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/partner-layer/partner-tenant-assignments?partner_id=' . $this->partner->partner_id);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    #[Test]
    public function authorized_user_can_soft_delete_assignment(): void
    {
        $assignment = PartnerTenantAssignment::create([
            'assignment_id'   => (string) Str::uuid(),
            'partner_id'      => $this->partner->partner_id,
            'tenant_id'       => (string) Str::uuid(),
            'assignment_type' => 1,
            'status'          => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/partner-layer/partner-tenant-assignments/' . $assignment->assignment_id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('partner_tenant_assignments', [
            'assignment_id' => $assignment->assignment_id,
        ]);
    }
}
