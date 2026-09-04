<?php

namespace Tests\Feature\Modules\Inventory;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Models\InventoryDocumentItem;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-08 — PostgreSQL RLS + TenantScoped isolation for Inventory operational tables.
 *
 * Tables under test:
 *   inv_items, inv_warehouses, inv_locations, inv_stock_batches,
 *   inv_stock_balances, inv_documents, inv_document_items
 *
 * Pattern follows AccountingRlsIsolationTest and Tenant Isolation Architecture Standard §4 / §10.
 */
class InventoryRlsIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;

    protected string $itemAId;
    protected string $itemBId;
    protected string $warehouseAId;
    protected string $warehouseBId;
    protected string $locationAId;
    protected string $locationBId;
    protected string $batchAId;
    protected string $batchBId;
    protected string $balanceAId;
    protected string $balanceBId;
    protected string $documentAId;
    protected string $documentBId;
    protected string $documentItemAId;
    protected string $documentItemBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureRlsInfrastructure([
            'inv_items',
            'inv_warehouses',
            'inv_locations',
            'inv_stock_batches',
            'inv_stock_balances',
            'inv_documents',
            'inv_document_items',
        ]);

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'INV_RLS_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'INV_RLS_B', 'status' => 1]);

        $this->itemAId = (string) Str::uuid();
        $this->itemBId = (string) Str::uuid();
        $this->warehouseAId = (string) Str::uuid();
        $this->warehouseBId = (string) Str::uuid();
        $this->locationAId = (string) Str::uuid();
        $this->locationBId = (string) Str::uuid();
        $this->batchAId = (string) Str::uuid();
        $this->batchBId = (string) Str::uuid();
        $this->balanceAId = (string) Str::uuid();
        $this->balanceBId = (string) Str::uuid();
        $this->documentAId = (string) Str::uuid();
        $this->documentBId = (string) Str::uuid();
        $this->documentItemAId = (string) Str::uuid();
        $this->documentItemBId = (string) Str::uuid();

        $groupA = (string) Str::uuid();
        $groupB = (string) Str::uuid();
        $uomA = (string) Str::uuid();
        $uomB = (string) Str::uuid();
        $branchA = (string) Str::uuid();
        $branchB = (string) Str::uuid();
        $periodA = (string) Str::uuid();
        $periodB = (string) Str::uuid();

        DB::table('inv_items')->insert([
            [
                'item_id' => $this->itemAId,
                'tenant_id' => $this->tenantA->tenant_id,
                'item_group_id' => $groupA,
                'uom_id' => $uomA,
                'code' => 'ITEM-A',
                'name' => 'Item A',
                'item_type' => 1,
                'valuation_method' => 1,
                'status' => 1,
                'created_at' => now(),
                'row_version' => 1,
            ],
            [
                'item_id' => $this->itemBId,
                'tenant_id' => $this->tenantB->tenant_id,
                'item_group_id' => $groupB,
                'uom_id' => $uomB,
                'code' => 'ITEM-B',
                'name' => 'Item B',
                'item_type' => 1,
                'valuation_method' => 1,
                'status' => 1,
                'created_at' => now(),
                'row_version' => 1,
            ],
        ]);

        DB::table('inv_warehouses')->insert([
            [
                'warehouse_id' => $this->warehouseAId,
                'tenant_id' => $this->tenantA->tenant_id,
                'branch_id' => $branchA,
                'code' => 'WH-A',
                'name' => 'Warehouse A',
                'is_bonded' => false,
                'status' => 1,
                'created_at' => now(),
                'row_version' => 1,
            ],
            [
                'warehouse_id' => $this->warehouseBId,
                'tenant_id' => $this->tenantB->tenant_id,
                'branch_id' => $branchB,
                'code' => 'WH-B',
                'name' => 'Warehouse B',
                'is_bonded' => false,
                'status' => 1,
                'created_at' => now(),
                'row_version' => 1,
            ],
        ]);

        DB::table('inv_locations')->insert([
            [
                'location_id' => $this->locationAId,
                'warehouse_id' => $this->warehouseAId,
                'parent_location_id' => null,
                'tenant_id' => $this->tenantA->tenant_id,
                'code' => 'LOC-A',
                'name' => 'Location A',
                'status' => 1,
                'created_at' => now(),
                'row_version' => 1,
            ],
            [
                'location_id' => $this->locationBId,
                'warehouse_id' => $this->warehouseBId,
                'parent_location_id' => null,
                'tenant_id' => $this->tenantB->tenant_id,
                'code' => 'LOC-B',
                'name' => 'Location B',
                'status' => 1,
                'created_at' => now(),
                'row_version' => 1,
            ],
        ]);

        DB::table('inv_stock_batches')->insert([
            [
                'batch_id' => $this->batchAId,
                'tenant_id' => $this->tenantA->tenant_id,
                'item_id' => $this->itemAId,
                'batch_number' => 'BATCH-A',
                'quantity_produced' => 100,
                'quantity_remaining' => 100,
                'qc_status' => 2,
                'created_at' => now(),
                'row_version' => 1,
            ],
            [
                'batch_id' => $this->batchBId,
                'tenant_id' => $this->tenantB->tenant_id,
                'item_id' => $this->itemBId,
                'batch_number' => 'BATCH-B',
                'quantity_produced' => 50,
                'quantity_remaining' => 50,
                'qc_status' => 2,
                'created_at' => now(),
                'row_version' => 1,
            ],
        ]);

        DB::table('inv_stock_balances')->insert([
            [
                'stock_balance_id' => $this->balanceAId,
                'tenant_id' => $this->tenantA->tenant_id,
                'warehouse_id' => $this->warehouseAId,
                'location_id' => $this->locationAId,
                'item_id' => $this->itemAId,
                'quantity_on_hand' => 10,
                'quantity_reserved' => 0,
                'updated_at' => now(),
                'row_version' => 1,
            ],
            [
                'stock_balance_id' => $this->balanceBId,
                'tenant_id' => $this->tenantB->tenant_id,
                'warehouse_id' => $this->warehouseBId,
                'location_id' => $this->locationBId,
                'item_id' => $this->itemBId,
                'quantity_on_hand' => 20,
                'quantity_reserved' => 0,
                'updated_at' => now(),
                'row_version' => 1,
            ],
        ]);

        DB::table('inv_documents')->insert([
            [
                'document_id' => $this->documentAId,
                'tenant_id' => $this->tenantA->tenant_id,
                'fiscal_period_id' => $periodA,
                'document_type' => 1,
                'document_number' => 'DOC-A-001',
                'posting_date' => now(),
                'status' => 1,
                'created_at' => now(),
                'row_version' => 1,
            ],
            [
                'document_id' => $this->documentBId,
                'tenant_id' => $this->tenantB->tenant_id,
                'fiscal_period_id' => $periodB,
                'document_type' => 1,
                'document_number' => 'DOC-B-001',
                'posting_date' => now(),
                'status' => 1,
                'created_at' => now(),
                'row_version' => 1,
            ],
        ]);

        DB::table('inv_document_items')->insert([
            [
                'document_item_id' => $this->documentItemAId,
                'document_id' => $this->documentAId,
                'tenant_id' => $this->tenantA->tenant_id,
                'item_id' => $this->itemAId,
                'to_location_id' => $this->locationAId,
                'quantity' => 5,
                'unit_cost' => 10,
                'sort_order' => 1,
                'created_at' => now(),
                'row_version' => 1,
            ],
            [
                'document_item_id' => $this->documentItemBId,
                'document_id' => $this->documentBId,
                'tenant_id' => $this->tenantB->tenant_id,
                'item_id' => $this->itemBId,
                'to_location_id' => $this->locationBId,
                'quantity' => 8,
                'unit_cost' => 12,
                'sort_order' => 1,
                'created_at' => now(),
                'row_version' => 1,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        try {
            DB::statement('RESET ROLE');
            DB::statement("SELECT set_config('app.current_tenant_id', '', true)");
        } catch (\Throwable $e) {
            // ignore cleanup errors
        }

        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function ensureRlsInfrastructure(array $tables): void
    {
        DB::statement('GRANT USAGE ON SCHEMA public TO app_user');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_user');

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$table}
                FOR ALL
                USING (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
                WITH CHECK (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
            ");
        }
    }

    protected function actAsAppUser(): void
    {
        DB::statement('SET ROLE app_user');
    }

    #[Test]
    public function rls_isolates_inv_items_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = Item::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->itemAId, $rows->first()->item_id);
    }

    #[Test]
    public function rls_isolates_inv_warehouses_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = Warehouse::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->warehouseAId, $rows->first()->warehouse_id);
    }

    #[Test]
    public function rls_isolates_inv_locations_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = Location::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->locationAId, $rows->first()->location_id);
    }

    #[Test]
    public function rls_isolates_inv_stock_batches_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = StockBatch::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->batchAId, $rows->first()->batch_id);
    }

    #[Test]
    public function rls_isolates_inv_stock_balances_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = StockBalance::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->balanceAId, $rows->first()->stock_balance_id);
    }

    #[Test]
    public function rls_isolates_inv_documents_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = InventoryDocument::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->documentAId, $rows->first()->document_id);
    }

    #[Test]
    public function rls_isolates_inv_document_items_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = InventoryDocumentItem::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->documentItemAId, $rows->first()->document_item_id);
    }

    #[Test]
    public function rls_returns_empty_when_tenant_context_missing(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', '', false)");

        $this->assertCount(0, Item::withoutGlobalScopes()->get());
        $this->assertCount(0, Warehouse::withoutGlobalScopes()->get());
        $this->assertCount(0, Location::withoutGlobalScopes()->get());
        $this->assertCount(0, StockBatch::withoutGlobalScopes()->get());
        $this->assertCount(0, StockBalance::withoutGlobalScopes()->get());
        $this->assertCount(0, InventoryDocument::withoutGlobalScopes()->get());
        $this->assertCount(0, InventoryDocumentItem::withoutGlobalScopes()->get());
    }

    #[Test]
    public function rls_blocks_cross_tenant_insert_on_inv_items(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('inv_items')->insert([
            'item_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->tenant_id,
            'item_group_id' => (string) Str::uuid(),
            'uom_id' => (string) Str::uuid(),
            'code' => 'HACK',
            'name' => 'Hack Item',
            'item_type' => 1,
            'valuation_method' => 1,
            'status' => 1,
            'created_at' => now(),
            'row_version' => 1,
        ]);
    }

    #[Test]
    public function tenant_scoped_global_scope_isolates_items(): void
    {
        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);

        $rows = Item::all();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->itemAId, $rows->first()->item_id);

        TenantContext::getInstance()->setTenantId($this->tenantB->tenant_id);
        app()->instance('current_tenant_id', $this->tenantB->tenant_id);

        $rowsB = Item::all();
        $this->assertCount(1, $rowsB);
        $this->assertEquals($this->itemBId, $rowsB->first()->item_id);
    }

    #[Test]
    public function tenant_scoped_global_scope_isolates_all_inventory_tables(): void
    {
        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);

        $this->assertCount(1, Warehouse::all());
        $this->assertCount(1, Location::all());
        $this->assertCount(1, StockBatch::all());
        $this->assertCount(1, StockBalance::all());
        $this->assertCount(1, InventoryDocument::all());
        $this->assertCount(1, InventoryDocumentItem::all());

        $this->assertEquals($this->warehouseAId, Warehouse::first()->warehouse_id);
        $this->assertEquals($this->locationAId, Location::first()->location_id);
        $this->assertEquals($this->batchAId, StockBatch::first()->batch_id);
        $this->assertEquals($this->balanceAId, StockBalance::first()->stock_balance_id);
        $this->assertEquals($this->documentAId, InventoryDocument::first()->document_id);
        $this->assertEquals($this->documentItemAId, InventoryDocumentItem::first()->document_item_id);
    }
}
