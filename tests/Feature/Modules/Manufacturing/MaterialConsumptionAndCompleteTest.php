<?php

namespace Tests\Feature\Modules\Manufacturing;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\CostLayer;
use App\Modules\Manufacturing\Services\BomService;
use App\Modules\Manufacturing\Services\MaterialConsumptionService;
use App\Modules\Manufacturing\Services\ProductionOrderService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-MFG-02 — issue materials (Inventory Issue) + complete (FG Receipt)
 */
class MaterialConsumptionAndCompleteTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $finishedItemId;
    protected string $materialItemId;
    protected string $locationId;
    protected string $warehouseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'MFG_CONS_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        Context::add('tenant_id', $this->tenantA->tenant_id);
        Context::add('user_id', $this->userA->user_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $finished = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'FG-01',
            'name'             => 'Finished Good',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->finishedItemId = $finished->item_id;

        $material = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'RM-01',
            'name'             => 'Raw Material',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->materialItemId = $material->item_id;

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-MFG',
            'name'         => 'MFG WH',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseId = $warehouse->warehouse_id;

        $loc = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-MFG',
            'name'         => 'Bin MFG',
            'status'       => 1,
        ]);
        $this->locationId = $loc->location_id;

        // Seed raw material stock
        StockBalance::withoutGlobalScopes()->create([
            'stock_balance_id'  => (string) Str::uuid(),
            'tenant_id'         => $this->tenantA->tenant_id,
            'warehouse_id'      => $this->warehouseId,
            'location_id'       => $this->locationId,
            'item_id'           => $this->materialItemId,
            'quantity_on_hand'  => 1000,
            'quantity_reserved' => 0,
            'row_version'       => 1,
            'updated_at'        => now(),
        ]);

        CostLayer::withoutGlobalScopes()->create([
            'cost_layer_id'           => (string) Str::uuid(),
            'tenant_id'               => $this->tenantA->tenant_id,
            'item_id'                 => $this->materialItemId,
            'location_id'             => $this->locationId,
            'quantity_remaining'      => 1000,
            'unit_cost'               => 5,
            'received_at'             => now()->subDay(),
            'source_document_id'      => (string) Str::uuid(),
            'source_document_item_id' => (string) Str::uuid(),
            'row_version'             => 1,
        ]);
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    #[Test]
    public function issue_materials_decreases_stock_and_complete_increases_finished_good(): void
    {
        $bomService = app(BomService::class);
        $poService = app(ProductionOrderService::class);
        $consService = app(MaterialConsumptionService::class);

        $bom = $bomService->create([
            'item_id'      => $this->finishedItemId,
            'version_code' => 'V1',
            'title'        => 'FG BOM',
            'items'        => [
                [
                    'material_item_id' => $this->materialItemId,
                    'quantity'         => 2.0, // 2 per finished unit
                    'scrap_percentage' => 0,
                ],
            ],
        ]);
        $bomService->approve($bom->bom_id);

        $order = $poService->create([
            'order_number'     => 'PO-CONS-01',
            'item_id'          => $this->finishedItemId,
            'bom_id'           => $bom->bom_id,
            'planned_quantity' => 10,
            'start_date'       => '2026-09-10',
            'due_date'         => '2026-09-20',
        ]);
        $poService->release($order->production_order_id);

        $lines = $consService->issueMaterials($order->production_order_id, $this->locationId);
        $this->assertCount(1, $lines);
        $this->assertSame(MaterialConsumptionService::STATUS_POSTED, (int) $lines->first()->status);
        $this->assertEquals(20.0, (float) $lines->first()->planned_quantity); // 2 * 10
        $this->assertEquals(20.0, (float) $lines->first()->actual_quantity);
        $this->assertNotEmpty($lines->first()->inventory_document_id);

        $rmBalance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->materialItemId)
            ->first();
        $this->assertEquals(980.0, (float) $rmBalance->quantity_on_hand);

        $order->refresh();
        $this->assertSame(ProductionOrderService::STATUS_IN_PROGRESS, (int) $order->status);

        $completed = $consService->completeProduction(
            $order->production_order_id,
            $this->locationId,
            10.0
        );
        $this->assertSame(ProductionOrderService::STATUS_COMPLETED, (int) $completed->status);
        $this->assertEquals(10.0, (float) $completed->produced_quantity);

        $fgBalance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->finishedItemId)
            ->first();
        $this->assertNotNull($fgBalance);
        $this->assertEquals(10.0, (float) $fgBalance->quantity_on_hand);

        $outbox = DB::table('event_outbox')
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->where('event_type', 'manufacturing.production_order.completed.v1')
            ->where('aggregate_id', $order->production_order_id)
            ->first();
        $this->assertNotNull($outbox);
    }
}
