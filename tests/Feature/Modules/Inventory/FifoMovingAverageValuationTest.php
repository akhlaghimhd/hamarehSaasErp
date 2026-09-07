<?php

namespace Tests\Feature\Modules\Inventory;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\CostLayer;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentDTO;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentItemDTO;
use App\Modules\Inventory\Services\InventoryDocumentService;
use App\Modules\Inventory\Services\InventoryDocumentItemService;
use App\Modules\Inventory\Services\ValuationService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-18 — FIFO vs Moving Average issue cost
 */
class FifoMovingAverageValuationTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $locationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'VAL_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        Context::add('tenant_id', $this->tenantA->tenant_id);
        Context::add('user_id', $this->userA->user_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $wh = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-VAL',
            'name'         => 'Val WH',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $loc = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $wh->warehouse_id,
            'code'         => 'BIN-VAL',
            'name'         => 'Bin',
            'status'       => 1,
        ]);
        $this->locationId = $loc->location_id;
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    private function makeItem(int $valuationMethod, string $code): Item
    {
        return Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => $code,
            'name'             => $code,
            'item_type'        => 1,
            'valuation_method' => $valuationMethod,
            'status'           => 1,
        ]);
    }

    private function postReceipt(string $itemId, float $qty, float $unitCost, string $docNo): void
    {
        $docs = app(InventoryDocumentService::class);
        $items = app(InventoryDocumentItemService::class);

        $doc = $docs->createDocument(new CreateInventoryDocumentDTO(
            fiscal_period_id: (string) Str::uuid(),
            document_type: InventoryDocumentService::TYPE_RECEIPT,
            document_number: $docNo,
            posting_date: now()->toDateString(),
        ));
        $items->createItem(new CreateInventoryDocumentItemDTO(
            document_id: $doc->document_id,
            item_id: $itemId,
            quantity: $qty,
            unit_cost: $unitCost,
            to_location_id: $this->locationId,
        ));
        $docs->postDocument($doc->document_id);
    }

    private function postIssue(string $itemId, float $qty, string $docNo): float
    {
        $docs = app(InventoryDocumentService::class);
        $items = app(InventoryDocumentItemService::class);

        $doc = $docs->createDocument(new CreateInventoryDocumentDTO(
            fiscal_period_id: (string) Str::uuid(),
            document_type: InventoryDocumentService::TYPE_ISSUE,
            document_number: $docNo,
            posting_date: now()->toDateString(),
        ));
        $items->createItem(new CreateInventoryDocumentItemDTO(
            document_id: $doc->document_id,
            item_id: $itemId,
            quantity: $qty,
            unit_cost: 0,
            from_location_id: $this->locationId,
        ));
        $posted = $docs->postDocument($doc->document_id);

        return (float) $posted->items->first()->unit_cost;
    }

    #[Test]
    public function fifo_consumes_oldest_layers_first(): void
    {
        $item = $this->makeItem(ValuationService::METHOD_FIFO, 'FIFO-1');

        $this->postReceipt($item->item_id, 100, 10.0, 'R-FIFO-1');
        $this->postReceipt($item->item_id, 50, 12.0, 'R-FIFO-2');

        // Issue 120 → 100@10 + 20@12 = avg 10.3333
        $unitCost = $this->postIssue($item->item_id, 120, 'I-FIFO-1');
        $this->assertEqualsWithDelta(10.3333, $unitCost, 0.001);

        $remaining = CostLayer::query()
            ->where('item_id', $item->item_id)
            ->where('quantity_remaining', '>', 0)
            ->get();
        $this->assertCount(1, $remaining);
        $this->assertEquals(30.0, (float) $remaining->first()->quantity_remaining);
        $this->assertEquals(12.0, (float) $remaining->first()->unit_cost);
    }

    #[Test]
    public function moving_average_blends_on_receipt_and_issues_at_avg(): void
    {
        $item = $this->makeItem(ValuationService::METHOD_MOVING_AVERAGE, 'MA-1');

        $this->postReceipt($item->item_id, 100, 10.0, 'R-MA-1');
        $this->postReceipt($item->item_id, 100, 14.0, 'R-MA-2');
        // avg = 12

        $unitCost = $this->postIssue($item->item_id, 50, 'I-MA-1');
        $this->assertEqualsWithDelta(12.0, $unitCost, 0.001);

        $layers = CostLayer::query()
            ->where('item_id', $item->item_id)
            ->where('quantity_remaining', '>', 0)
            ->get();
        $this->assertEquals(150.0, (float) $layers->sum('quantity_remaining'));
    }
}
