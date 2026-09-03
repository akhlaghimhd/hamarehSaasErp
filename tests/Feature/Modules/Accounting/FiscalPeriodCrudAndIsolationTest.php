<?php

namespace Tests\Feature\Modules\Accounting;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\Accounting\Models\FiscalPeriod;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-ACC-02.2 — FiscalPeriod full CRUD + closePeriod + isolation + soft-delete + row_version
 */
class FiscalPeriodCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'FP_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'FP_B', 'status' => 1]);

        $this->userA = User::factory()->create(['status' => 1]);
        $this->userB = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'user_id'   => $this->userB->user_id,
            'status'    => 1,
        ]);

        $roleA = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'fp-full',
            'name'      => 'Fiscal Full',
            'status'    => 1,
        ]);
        $roleB = TenantRole::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'code'      => 'fp-full',
            'name'      => 'Fiscal Full',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'accounting.fiscal-period.view',
            'accounting.fiscal-period.create',
            'accounting.fiscal-period.update',
            'accounting.fiscal-period.close',
            'accounting.fiscal-period.delete',
        ];

        foreach ([$this->tenantA, $this->tenantB] as $tenant) {
            $role = $tenant->tenant_id === $this->tenantA->tenant_id ? $roleA : $roleB;
            $user = $tenant->tenant_id === $this->tenantA->tenant_id ? $this->userA : $this->userB;

            foreach ($permissionCodes as $code) {
                $perm = TenantPermission::create([
                    'tenant_permission_id' => (string) Str::uuid(),
                    'tenant_id'            => $tenant->tenant_id,
                    'code'                 => $code,
                    'name'                 => $code,
                    'module_name'          => 'Accounting',
                    'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
                    'status'               => 1,
                ]);
                TenantRolePermission::create([
                    'tenant_role_permission_id' => (string) Str::uuid(),
                    'tenant_id'                 => $tenant->tenant_id,
                    'tenant_role_id'            => $role->tenant_role_id,
                    'tenant_permission_id'      => $perm->tenant_permission_id,
                ]);
            }

            TenantUserRole::create([
                'tenant_user_role_id' => (string) Str::uuid(),
                'tenant_id'           => $tenant->tenant_id,
                'user_id'             => $user->user_id,
                'tenant_role_id'      => $role->tenant_role_id,
            ]);
        }

        $this->tokenA = $this->userA->createToken('fp-a', ['tenant:' . $this->tenantA->tenant_id])->plainTextToken;
        $this->tokenB = $this->userB->createToken('fp-b', ['tenant:' . $this->tenantB->tenant_id])->plainTextToken;

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

    protected function authHeaders(string $token, string $tenantId): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $tenantId,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function can_create_list_show_update_close_and_soft_delete_fiscal_period(): void
    {
        $create = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/fiscal-periods', [
                'name'       => 'FY2026',
                'start_date' => '2026-01-01',
                'end_date'   => '2026-12-31',
            ]);

        $create->assertStatus(201);
        $periodId = $create->json('data.period_id');
        $this->assertNotEmpty($periodId);

        $this->assertDatabaseHas('fin_fiscal_periods', [
            'period_id'   => $periodId,
            'tenant_id'   => $this->tenantA->tenant_id,
            'name'        => 'FY2026',
            'is_closed'   => false,
            'row_version' => 1,
        ]);

        $list = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/fiscal-periods');
        $list->assertStatus(200);
        $ids = collect($list->json('data'))->pluck('period_id')->all();
        $this->assertContains($periodId, $ids);

        $show = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/fiscal-periods/' . $periodId);
        $show->assertStatus(200)->assertJsonPath('data.name', 'FY2026');

        $update = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/accounting/fiscal-periods/' . $periodId, [
                'name' => 'FY2026 Updated',
            ]);
        $update->assertStatus(200);

        $this->assertDatabaseHas('fin_fiscal_periods', [
            'period_id'   => $periodId,
            'name'        => 'FY2026 Updated',
            'row_version' => 2,
        ]);

        $close = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/fiscal-periods/' . $periodId . '/close');
        $close->assertStatus(200);

        $this->assertDatabaseHas('fin_fiscal_periods', [
            'period_id' => $periodId,
            'is_closed' => true,
        ]);

        // Closed period cannot be updated
        $updateClosed = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/accounting/fiscal-periods/' . $periodId, ['name' => 'Nope']);
        $this->assertTrue(in_array($updateClosed->status(), [409, 422, 400]));

        // Closed period cannot be deleted
        $deleteClosed = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/accounting/fiscal-periods/' . $periodId);
        $this->assertTrue(in_array($deleteClosed->status(), [409, 422, 400]));

        // Soft-delete an open period
        $create2 = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/fiscal-periods', [
                'name'       => 'FY2027',
                'start_date' => '2027-01-01',
                'end_date'   => '2027-12-31',
            ]);
        $create2->assertStatus(201);
        $periodId2 = $create2->json('data.period_id');

        $delete = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/accounting/fiscal-periods/' . $periodId2);
        $delete->assertStatus(200);
        $this->assertSoftDeleted('fin_fiscal_periods', ['period_id' => $periodId2]);
    }

    #[Test]
    public function tenant_isolation_prevents_cross_tenant_access(): void
    {
        $create = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/fiscal-periods', [
                'name'       => 'A-Period',
                'start_date' => '2025-01-01',
                'end_date'   => '2025-12-31',
            ]);
        $create->assertStatus(201);
        $periodIdA = $create->json('data.period_id');

        $showAsB = $this->withHeaders($this->authHeaders($this->tokenB, $this->tenantB->tenant_id))
            ->getJson('/api/accounting/fiscal-periods/' . $periodIdA);
        $this->assertTrue(in_array($showAsB->status(), [403, 404]));

        $periodB = FiscalPeriod::withoutGlobalScopes()->create([
            'period_id'   => (string) Str::uuid(),
            'tenant_id'   => $this->tenantB->tenant_id,
            'name'        => 'B-Period',
            'start_date'  => '2025-01-01',
            'end_date'    => '2025-12-31',
            'is_closed'   => false,
            'row_version' => 1,
        ]);

        $listAsA = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/fiscal-periods');
        $listAsA->assertStatus(200);
        $ids = collect($listAsA->json('data'))->pluck('period_id')->all();
        $this->assertNotContains($periodB->period_id, $ids);
        $this->assertContains($periodIdA, $ids);
    }

    #[Test]
    public function overlapping_dates_are_rejected(): void
    {
        $first = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/fiscal-periods', [
                'name'       => 'Q1',
                'start_date' => '2026-01-01',
                'end_date'   => '2026-03-31',
            ]);
        $first->assertStatus(201);

        $overlap = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/fiscal-periods', [
                'name'       => 'Overlap',
                'start_date' => '2026-03-01',
                'end_date'   => '2026-06-30',
            ]);

        $this->assertTrue(
            in_array($overlap->status(), [409, 422, 400]),
            'Expected conflict on overlap, got ' . $overlap->status()
        );
    }

    #[Test]
    public function unauthorized_user_cannot_create_fiscal_period(): void
    {
        $unauthUser = User::factory()->create(['status' => 1]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $unauthUser->user_id,
            'status'    => 1,
        ]);
        $token = $unauthUser->createToken('fp-unauth', ['tenant:' . $this->tenantA->tenant_id])->plainTextToken;

        $response = $this->withHeaders($this->authHeaders($token, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/fiscal-periods', [
                'name'       => 'X',
                'start_date' => '2028-01-01',
                'end_date'   => '2028-12-31',
            ]);

        $this->assertTrue(in_array($response->status(), [401, 403]));
    }
}
