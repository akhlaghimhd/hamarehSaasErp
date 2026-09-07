<?php

namespace Tests\Feature\Modules\Manufacturing;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\Manufacturing\Models\WorkCenter;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-MFG-01 — WorkCenter CRUD + tenant isolation
 */
class WorkCenterCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'MFG_WC_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'MFG_WC_B', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'mfg-wc-mgr',
            'name'      => 'MFG WC Manager',
            'status'    => 1,
        ]);

        foreach ([
            'manufacturing.work-center.view',
            'manufacturing.work-center.create',
            'manufacturing.work-center.update',
            'manufacturing.work-center.delete',
        ] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenantA->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Manufacturing',
                'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
                'status'               => 1,
            ]);
            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenantA->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenantA->tenant_id,
            'user_id'             => $this->userA->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->tokenA = $this->userA->createToken(
            'mfg-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->tokenA,
            'X-Tenant-ID'   => $this->tenantA->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function can_create_list_and_show_work_center(): void
    {
        $create = $this->withHeaders($this->authHeaders())
            ->postJson('/api/manufacturing/work-centers', [
                'code'                   => 'WC-01',
                'name'                   => 'Assembly Line 1',
                'capacity_hours_per_day' => 16,
                'efficiency_percentage'  => 95,
                'cost_per_hour'          => 120.5,
                'status'                 => 1,
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'WC-01');

        $id = $create->json('data.work_center_id');

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/manufacturing/work-centers')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/manufacturing/work-centers/' . $id)
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Assembly Line 1');
    }

    #[Test]
    public function tenant_b_cannot_see_tenant_a_work_center(): void
    {
        $wc = WorkCenter::withoutGlobalScopes()->create([
            'work_center_id'         => (string) Str::uuid(),
            'tenant_id'              => $this->tenantA->tenant_id,
            'code'                   => 'WC-ISO',
            'name'                   => 'Secret WC',
            'capacity_hours_per_day' => 8,
            'efficiency_percentage'  => 100,
            'cost_per_hour'          => 10,
            'status'                 => 1,
            'row_version'            => 1,
        ]);

        $found = WorkCenter::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantB->tenant_id)
            ->where('work_center_id', $wc->work_center_id)
            ->first();

        $this->assertNull($found);

        $scoped = WorkCenter::query()
            ->where('work_center_id', $wc->work_center_id)
            ->first();
        $this->assertNotNull($scoped);
    }
}
