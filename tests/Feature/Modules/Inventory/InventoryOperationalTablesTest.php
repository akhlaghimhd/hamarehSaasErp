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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-02 — operational tables exist + tenant isolation on models
 */
class InventoryOperationalTablesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_OPS_A',
            'status'      => 1,
        ]);
        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'INV_OPS_B',
            'status'      => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
    }

    #[Test]
    public function operational_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('inv_locations'));
        $this->assertTrue(Schema::hasTable('inv_stock_batches'));
        $this->assertTrue(Schema::hasTable('inv_stock_balances'));
        $this->assertTrue(Schema::hasTable('inv_documents'));
        $this->assertTrue(Schema::hasTable('inv_document_items'));
    }

    #[Test]
    public function generated_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('inv_stock_balances', 'quantity_available'));
        $this->assertTrue(Schema::hasColumn('inv_document_items', 'total_cost'));
    }

    #[Test]
    public function location_is_tenant_isolated(): void
    {
        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-OPS-A',
            'name'         => 'Ops Warehouse A',
            'is_bonded'    => false,
            'status'       => 1,
        ]);

        Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'LOC-A-01',
            'name'         => 'Aisle A',
            'status'       => 1,
        ]);

        $warehouseB = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantB->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-OPS-B',
            'name'         => 'Ops Warehouse B',
            'is_bonded'    => false,
            'status'       => 1,
        ]);

        Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantB->tenant_id,
            'warehouse_id' => $warehouseB->warehouse_id,
            'code'         => 'LOC-B-01',
            'name'         => 'Tenant B Loc',
            'status'       => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);

        $visible = Location::query()->get();
        $this->assertCount(1, $visible);
        $this->assertSame('LOC-A-01', $visible->first()->code);
    }

    #[Test]
    public function inventory_document_and_items_can_be_created_with_generated_total_cost(): void
    {
        $item = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITEM-OPS-01',
            'name'             => 'Ops Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-DOC-A',
            'name'         => 'Doc Warehouse',
            'is_bonded'    => false,
            'status'       => 1,
        ]);

        $location = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-01',
            'name'         => 'Bin 01',
            'status'       => 1,
        ]);

        $document = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-2026-0001',
            'status'           => 1,
        ]);

        $line = InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id'      => $document->document_id,
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_id'          => $item->item_id,
            'to_location_id'   => $location->location_id,
            'quantity'         => 10.5,
            'unit_cost'        => 100,
            'sort_order'       => 1,
        ]);

        $line->refresh();
        $this->assertEquals('1050.0000', (string) $line->total_cost);

        $balance = StockBalance::withoutGlobalScopes()->create([
            'stock_balance_id'  => (string) Str::uuid(),
            'tenant_id'         => $this->tenantA->tenant_id,
            'warehouse_id'      => $warehouse->warehouse_id,
            'location_id'       => $location->location_id,
            'item_id'           => $item->item_id,
            'quantity_on_hand'  => 10.5,
            'quantity_reserved' => 2.5,
        ]);

        $balance->refresh();
        $this->assertEquals('8.0000', (string) $balance->quantity_available);

        $batch = StockBatch::withoutGlobalScopes()->create([
            'batch_id'            => (string) Str::uuid(),
            'tenant_id'           => $this->tenantA->tenant_id,
            'item_id'             => $item->item_id,
            'batch_number'        => 'BATCH-001',
            'quantity_produced'   => 100,
            'quantity_remaining'  => 100,
            'qc_status'           => 1,
        ]);

        $this->assertDatabaseHas('inv_stock_batches', [
            'batch_id'     => $batch->batch_id,
            'batch_number' => 'BATCH-001',
        ]);
    }
}
