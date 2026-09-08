<?php

namespace Tests\Feature\Modules\ProcurementSales;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\CostLayer;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Services\InventoryDocumentService;
use App\Modules\Inventory\Services\SalesDeliveryStockIssueService;
use App\Modules\ProcurementSales\DTOs\CreateSalesDeliveryOrderDTO;
use App\Modules\ProcurementSales\DTOs\SalesDeliveryOrderItemDTO;
use App\Modules\ProcurementSales\Services\SalesDeliveryOrderService;
use App\Modules\ProcurementSales\Events\SalesDeliveryPostedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-PS-05 — Sales Delivery create/post + Inventory Issue + release reservation
 */
class SalesDeliveryPostAndIssueTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $itemId;
    protected string $warehouseId;
    protected string $locationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'PS_SDO_A',
            'status'      => 1,
        ]);

        $this->userA = User::factory()->create(['status' => 1]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        Context::add('tenant_id', $this->tenantA->tenant_id);
        Context::add('user_id', $this->userA->user_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $item = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITEM-SDO-01',
            'name'             => 'SDO Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemId = $item->item_id;

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'code'         => 'WH-SDO',
            'name'         => 'SDO Warehouse',
            'status'       => 1,
        ]);
        $this->warehouseId = $warehouse->warehouse_id;

        $loc = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-SDO-1',
            'name'         => 'Bin SDO 1',
            'status'       => 1,
        ]);
        $this->locationId = $loc->location_id;

        StockBalance::withoutGlobalScopes()->create([
            'tenant_id'         => $this->tenantA->tenant_id,
            'warehouse_id'      => $this->warehouseId,
            'location_id'       => $this->locationId,
            'item_id'           => $this->itemId,
            'quantity_on_hand'  => 50,
            'quantity_reserved' => 10,
            'row_version'       => 1,
            'updated_at'        => now(),
        ]);

        // ValuationService requires cost layers for ISSUE (FIFO)
        CostLayer::withoutGlobalScopes()->create([
            'cost_layer_id'           => (string) Str::uuid(),
            'tenant_id'               => $this->tenantA->tenant_id,
            'item_id'                 => $this->itemId,
            'location_id'             => $this->locationId,
            'quantity_remaining'      => 50,
            'unit_cost'               => 10,
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
    public function can_create_prepared_delivery_order(): void
    {
        $service = app(SalesDeliveryOrderService::class);

        $dto = new CreateSalesDeliveryOrderDTO(
            customerId: (string) Str::uuid(),
            warehouseId: $this->warehouseId,
            shippingDate: '2026-09-07 10:00:00',
            salesOrderId: (string) Str::uuid(),
            items: [
                new SalesDeliveryOrderItemDTO(
                    itemId: $this->itemId,
                    deliveredQuantity: 5.0,
                    unitPrice: 15.0,
                ),
            ],
        );

        $delivery = $service->createDeliveryOrder($dto);

        $this->assertNotEmpty($delivery->delivery_order_id);
        $this->assertSame(SalesDeliveryOrderService::STATUS_PREPARED, (int) $delivery->status);
        $this->assertSame($this->warehouseId, $delivery->warehouse_id);
        $this->assertCount(1, $delivery->items);
        $this->assertEquals(5.0, (float) $delivery->items->first()->delivered_quantity);
    }

    #[Test]
    public function post_publishes_outbox_and_issues_stock_via_listener(): void
    {
        $service = app(SalesDeliveryOrderService::class);
        $salesOrderId = (string) Str::uuid();

        $dto = new CreateSalesDeliveryOrderDTO(
            customerId: (string) Str::uuid(),
            warehouseId: $this->warehouseId,
            shippingDate: '2026-09-07 12:00:00',
            salesOrderId: $salesOrderId,
            items: [
                new SalesDeliveryOrderItemDTO(
                    itemId: $this->itemId,
                    deliveredQuantity: 8.0,
                    unitPrice: 12.0,
                ),
            ],
        );

        $delivery = $service->createDeliveryOrder($dto);
        $posted = $service->post($delivery->delivery_order_id);

        $this->assertSame(SalesDeliveryOrderService::STATUS_DISPATCHED, (int) $posted->status);

        $outbox = DB::table('event_outbox')
            ->where('aggregate_id', $delivery->delivery_order_id)
            ->where('event_type', SalesDeliveryPostedV1::EVENT_TYPE)
            ->first();

        $this->assertNotNull($outbox);
        $payload = json_decode($outbox->payload, true);
        $this->assertSame($this->warehouseId, $payload['warehouse_id']);
        $this->assertCount(1, $payload['lines']);

        event(SalesDeliveryPostedV1::EVENT_TYPE, [$payload]);

        $doc = InventoryDocument::query()
            ->where('source_document_type', SalesDeliveryStockIssueService::SOURCE_TYPE)
            ->where('source_document_id', $delivery->delivery_order_id)
            ->with('items')
            ->first();

        $this->assertNotNull($doc);
        $this->assertSame(InventoryDocumentService::TYPE_ISSUE, (int) $doc->document_type);
        $this->assertSame(InventoryDocumentService::STATUS_POSTED, (int) $doc->status);
        $this->assertCount(1, $doc->items);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();

        // on_hand 50 - 8 = 42; reserved released by 8 → 10-8=2
        $this->assertEquals(42.0, (float) $balance->quantity_on_hand);
        $this->assertEquals(2.0, (float) $balance->quantity_reserved);
    }

    #[Test]
    public function issue_service_is_idempotent(): void
    {
        $payload = [
            'event_type'        => SalesDeliveryPostedV1::EVENT_TYPE,
            'tenant_id'         => $this->tenantA->tenant_id,
            'delivery_order_id' => (string) Str::uuid(),
            'delivery_number'   => 'SDO-IDEM',
            'customer_id'       => (string) Str::uuid(),
            'sales_order_id'    => (string) Str::uuid(),
            'shipping_date'     => '2026-09-07',
            'warehouse_id'      => $this->warehouseId,
            'posted_by'         => $this->userA->user_id,
            'lines'             => [
                [
                    'item_id'     => $this->itemId,
                    'quantity'    => '3.0000',
                    'unit_price'  => '10.0000',
                    'line_number' => 1,
                ],
            ],
        ];

        $svc = app(SalesDeliveryStockIssueService::class);
        $first = $svc->issueFromPostedDelivery($payload);
        $second = $svc->issueFromPostedDelivery($payload);

        $this->assertSame($first->document_id, $second->document_id);
        $this->assertSame(
            1,
            InventoryDocument::query()
                ->where('source_document_type', SalesDeliveryStockIssueService::SOURCE_TYPE)
                ->where('source_document_id', $payload['delivery_order_id'])
                ->count()
        );

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();

        // only one issue of 3
        $this->assertEquals(47.0, (float) $balance->quantity_on_hand);
    }
}
