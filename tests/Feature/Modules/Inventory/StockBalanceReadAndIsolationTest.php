<?php

namespace Tests\Feature\Modules\Inventory;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBalance;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-09 — StockBalance read API (list/show) + tenant isolation
 * Balances are not creatable via API; seed via model for isolation checks.
 */
class StockBalanceReadAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $tokenA;
    protected string $warehouseAId;
    protected string $warehouseBId;
    protected string $locationAId;
    protected string $locationBId;
    protected string $itemAId;
    protected string $itemBId;
    protected string $balanceAId;
    protected string $balanceBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_BAL_A',
            'status'      => 1,
        ]);

        $this->userA = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'inv-bal-viewer',
            'name'      => 'Stock Balance Viewer',
            'status'    => 1,
        ]);

        $perm = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenantA->tenant_id,
            'code'                 => 'inventory.stock-balance.view',
            'name'                 => 'inventory.stock-balance.view',
            'module_name'          => 'Inventory',
            'action_type'          => 'VIEW',
            'status'               => 1,
        ]);

        TenantRolePermission::create([
            'tenant_role_permission_id' => (string) Str::uuid(),
            'tenant_id'                 => $this->tenantA->tenant_id,
            'tenant_role_id'            => $role->tenant_role_id,
            'tenant_permission_id'      => $perm->tenant_permission_id,
        ]);

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenantA->tenant_id,
            'user_id'             => $this->userA->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->tokenA = $this->userA->createToken(
            'bal-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'INV_BAL_B',
            'status'      => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $this->itemAId = (string) Str::uuid();
        $this->itemBId = (string) Str::uuid();
        $this->warehouseAId = (string) Str::uuid();
        $this->warehouseBId = (string) Str::uuid();
        $this->locationAId = (string) Str::uuid();
        $this->locationBId = (string) Str::uuid();
        $this->balanceAId = (string) Str::uuid();
        $this->balanceBId = (string) Str::uuid();

        Item::withoutGlobalScopes()->create([
            'item_id'          => $this->itemAId,
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITEM-BAL-A',
            'name'             => 'Item A',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);

        Item::withoutGlobalScopes()->create([
            'item_id'          => $this->itemBId,
            'tenant_id'        => $this->tenantB->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITEM-BAL-B',
            'name'             => 'Item B',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);

        Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => $this->warehouseAId,
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-BAL-A',
            'name'         => 'Warehouse A',
            'is_bonded'    => false,
            'status'       => 1,
        ]);

        Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => $this->warehouseBId,
            'tenant_id'    => $this->tenantB->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-BAL-B',
            'name'         => 'Warehouse B',
            'is_bonded'    => false,
            'status'       => 1,
        ]);

        Location::withoutGlobalScopes()->create([
            'location_id'  => $this->locationAId,
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $this->warehouseAId,
            'code'         => 'LOC-BAL-A',
            'name'         => 'Location A',
            'status'       => 1,
        ]);

        Location::withoutGlobalScopes()->create([
            'location_id'  => $this->locationBId,
            'tenant_id'    => $this->tenantB->tenant_id,
            'warehouse_id' => $this->warehouseBId,
            'code'         => 'LOC-BAL-B',
            'name'         => 'Location B',
            'status'       => 1,
        ]);

        StockBalance::withoutGlobalScopes()->create([
            'stock_balance_id'   => $this->balanceAId,
            'tenant_id'          => $this->tenantA->tenant_id,
            'warehouse_id'       => $this->warehouseAId,
            'location_id'        => $this->locationAId,
            'item_id'            => $this->itemAId,
            'quantity_on_hand'   => 25.0000,
            'quantity_reserved'  => 5.0000,
            'row_version'        => 1,
        ]);

        StockBalance::withoutGlobalScopes()->create([
            'stock_balance_id'   => $this->balanceBId,
            'tenant_id'          => $this->tenantB->tenant_id,
            'warehouse_id'       => $this->warehouseBId,
            'location_id'        => $this->locationBId,
            'item_id'            => $this->itemBId,
            'quantity_on_hand'   => 40.0000,
            'quantity_reserved'  => 0.0000,
            'row_version'        => 1,
        ]);
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
    public function can_list_and_show_own_stock_balance_with_quantity_available(): void
    {
        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-balances');

        $indexResponse->assertStatus(200)->assertJsonPath('success', true);

        $ids = collect($indexResponse->json('data'))->pluck('stock_balance_id')->toArray();
        $this->assertContains($this->balanceAId, $ids);
        $this->assertNotContains($this->balanceBId, $ids);

        $row = collect($indexResponse->json('data'))
            ->firstWhere('stock_balance_id', $this->balanceAId);

        $this->assertNotNull($row);
        $this->assertEquals('25.0000', (string) $row['quantity_on_hand']);
        $this->assertEquals('5.0000', (string) $row['quantity_reserved']);
        // GENERATED: quantity_available = on_hand - reserved
        $this->assertEquals('20.0000', (string) $row['quantity_available']);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-balances/' . $this->balanceAId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stock_balance_id', $this->balanceAId)
            ->assertJsonPath('data.item_id', $this->itemAId);
    }

    #[Test]
    public function can_filter_stock_balances_by_warehouse_location_and_item(): void
    {
        $byWarehouse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-balances?warehouse_id=' . $this->warehouseAId);

        $byWarehouse->assertStatus(200);
        $this->assertCount(1, $byWarehouse->json('data'));

        $byLocation = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-balances?location_id=' . $this->locationAId);

        $byLocation->assertStatus(200);
        $this->assertCount(1, $byLocation->json('data'));

        $byItem = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-balances?item_id=' . $this->itemAId);

        $byItem->assertStatus(200);
        $this->assertCount(1, $byItem->json('data'));

        $noMatch = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-balances?item_id=' . $this->itemBId);

        $noMatch->assertStatus(200);
        $this->assertCount(0, $noMatch->json('data'));
    }

    #[Test]
    public function tenant_a_cannot_see_stock_balance_of_tenant_b(): void
    {
        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-balances');

        $indexResponse->assertStatus(200);
        $ids = collect($indexResponse->json('data'))->pluck('stock_balance_id')->toArray();
        $this->assertNotContains($this->balanceBId, $ids);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-balances/' . $this->balanceBId);

        $showResponse->assertStatus(404);
    }
}
