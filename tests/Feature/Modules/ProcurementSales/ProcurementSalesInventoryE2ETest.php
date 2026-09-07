<?php

namespace Tests\Feature\Modules\ProcurementSales;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Services\InventoryDocumentService;
use App\Modules\Inventory\Services\PurchaseReceiptGoodsReceiptService;
use App\Modules\Inventory\Services\SalesDeliveryStockIssueService;
use App\Modules\ProcurementSales\DTOs\CreateSalesOrderDTO;
use App\Modules\ProcurementSales\DTOs\SalesOrderItemDTO;
use App\Modules\ProcurementSales\DTOs\CreateSalesDeliveryOrderDTO;
use App\Modules\ProcurementSales\DTOs\SalesDeliveryOrderItemDTO;
use App\Modules\ProcurementSales\Services\SalesOrderService;
use App\Modules\ProcurementSales\Services\SalesDeliveryOrderService;
use App\Modules\ProcurementSales\Events\PurchaseReceiptPostedV1;
use App\Modules\ProcurementSales\Events\SalesOrderConfirmedV1;
use App\Modules\ProcurementSales\Events\SalesDeliveryPostedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-PS-07 — End-to-end: Purchase Receipt → GR stock up → SO reserve → Delivery Issue.
 * Cross-module boundary via Outbox event names (no physical FK).
 */
class ProcurementSalesInventoryE2ETest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $itemId;
    protected string $warehouseId;
    protected string $locationId;
    protected string $currencyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'PS_E2E_A',
            'status'      => 1,
        ]);

        $this->userA = User::factory()->create(['status' => 1]);
        $this->currencyId = (string) Str::uuid();

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
            'code'             => 'ITEM-E2E-01',
            'name'             => 'E2E Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemId = $item->item_id;

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-E2E',
            'name'         => 'E2E Warehouse',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseId = $warehouse->warehouse_id;

        $loc = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-E2E-1',
            'name'         => 'Bin E2E 1',
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

    #[Test]
    public function full_purchase_to_sales_stock_loop(): void
    {
        // --- 1) Purchase Receipt posted → Goods Receipt → on_hand += 20 ---
        $purchaseReceiptId = (string) Str::uuid();
        $prPayload = [
            'event_type'          => PurchaseReceiptPostedV1::EVENT_TYPE,
            'tenant_id'           => $this->tenantA->tenant_id,
            'purchase_receipt_id' => $purchaseReceiptId,
            'receipt_number'      => 'PR-E2E-001',
            'supplier_id'         => (string) Str::uuid(),
            'purchase_order_id'   => (string) Str::uuid(),
            'receipt_date'        => '2026-09-07',
            'warehouse_id'        => $this->warehouseId,
            'posted_by'           => $this->userA->user_id,
            'lines'               => [
                [
                    'item_id'     => $this->itemId,
                    'quantity'    => '20.0000',
                    'unit_price'  => '10.0000',
                    'line_number' => 1,
                ],
            ],
        ];

        event(PurchaseReceiptPostedV1::EVENT_TYPE, [$prPayload]);

        $gr = InventoryDocument::query()
            ->where('source_document_type', PurchaseReceiptGoodsReceiptService::SOURCE_TYPE)
            ->where('source_document_id', $purchaseReceiptId)
            ->first();

        $this->assertNotNull($gr);
        $this->assertSame(InventoryDocumentService::STATUS_POSTED, (int) $gr->status);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(20.0, (float) $balance->quantity_on_hand);
        $this->assertEquals(0.0, (float) $balance->quantity_reserved);

        // --- 2) Sales Order confirm → reserve 7 ---
        $soService = app(SalesOrderService::class);
        $order = $soService->createSalesOrder(new CreateSalesOrderDTO(
            customerId: (string) Str::uuid(),
            currencyId: $this->currencyId,
            orderDate: '2026-09-07',
            deliveryDate: null,
            warehouseId: $this->warehouseId,
            items: [
                new SalesOrderItemDTO(
                    itemId: $this->itemId,
                    quantity: 7.0,
                    unitPrice: 25.0,
                ),
            ],
        ));

        $confirmed = $soService->confirm($order->sales_order_id);
        $this->assertSame(SalesOrderService::STATUS_CONFIRMED, (int) $confirmed->status);

        $soOutbox = DB::table('event_outbox')
            ->where('aggregate_id', $order->sales_order_id)
            ->where('event_type', SalesOrderConfirmedV1::EVENT_TYPE)
            ->first();
        $this->assertNotNull($soOutbox);

        $soPayload = json_decode($soOutbox->payload, true);
        event(SalesOrderConfirmedV1::EVENT_TYPE, [$soPayload]);

        $balance->refresh();
        $this->assertEquals(20.0, (float) $balance->quantity_on_hand);
        $this->assertEquals(7.0, (float) $balance->quantity_reserved);

        // --- 3) Sales Delivery post → Issue 7 + release reserve ---
        $deliveryService = app(SalesDeliveryOrderService::class);
        $delivery = $deliveryService->createDeliveryOrder(new CreateSalesDeliveryOrderDTO(
            customerId: $confirmed->customer_id,
            warehouseId: $this->warehouseId,
            shippingDate: '2026-09-08 09:00:00',
            salesOrderId: $order->sales_order_id,
            items: [
                new SalesDeliveryOrderItemDTO(
                    itemId: $this->itemId,
                    deliveredQuantity: 7.0,
                    unitPrice: 25.0,
                ),
            ],
        ));

        $posted = $deliveryService->post($delivery->delivery_order_id);
        $this->assertSame(SalesDeliveryOrderService::STATUS_DISPATCHED, (int) $posted->status);

        $delOutbox = DB::table('event_outbox')
            ->where('aggregate_id', $delivery->delivery_order_id)
            ->where('event_type', SalesDeliveryPostedV1::EVENT_TYPE)
            ->first();
        $this->assertNotNull($delOutbox);

        $delPayload = json_decode($delOutbox->payload, true);
        event(SalesDeliveryPostedV1::EVENT_TYPE, [$delPayload]);

        $gi = InventoryDocument::query()
            ->where('source_document_type', SalesDeliveryStockIssueService::SOURCE_TYPE)
            ->where('source_document_id', $delivery->delivery_order_id)
            ->first();

        $this->assertNotNull($gi);
        $this->assertSame(InventoryDocumentService::TYPE_ISSUE, (int) $gi->document_type);
        $this->assertSame(InventoryDocumentService::STATUS_POSTED, (int) $gi->status);

        $balance->refresh();
        // 20 received - 7 issued = 13 on hand; reserved fully released for the 7
        $this->assertEquals(13.0, (float) $balance->quantity_on_hand);
        $this->assertEquals(0.0, (float) $balance->quantity_reserved);
    }
}
