<?php

namespace Tests\Feature\Modules\ProcurementSales;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\ProcurementSales\DTOs\CreateSalesOrderDTO;
use App\Modules\ProcurementSales\DTOs\SalesOrderItemDTO;
use App\Modules\ProcurementSales\Services\SalesOrderService;
use App\Modules\ProcurementSales\Events\SalesOrderConfirmedV1;
use App\Modules\ProcurementSales\Models\SalesOrder;
use App\Modules\Inventory\Services\SalesOrderStockReservationService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-PS-05 — Sales Order create/confirm + Outbox + Inventory soft reservation
 */
class SalesOrderConfirmAndReservationTest extends TestCase
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
            'tenant_code' => 'PS_SO_A',
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
            'code'             => 'ITEM-SO-01',
            'name'             => 'SO Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemId = $item->item_id;

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-SO',
            'name'         => 'SO Warehouse',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseId = $warehouse->warehouse_id;

        $loc = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-SO-1',
            'name'         => 'Bin SO 1',
            'status'       => 1,
        ]);
        $this->locationId = $loc->location_id;

        // Seed available stock so reservation can succeed
        StockBalance::withoutGlobalScopes()->create([
            'tenant_id'         => $this->tenantA->tenant_id,
            'warehouse_id'      => $this->warehouseId,
            'location_id'       => $this->locationId,
            'item_id'           => $this->itemId,
            'quantity_on_hand'  => 100,
            'quantity_reserved' => 0,
            'row_version'       => 1,
            'updated_at'        => now(),
        ]);
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    #[Test]
    public function can_create_draft_sales_order_with_items(): void
    {
        $service = app(SalesOrderService::class);

        $dto = new CreateSalesOrderDTO(
            customerId: (string) Str::uuid(),
            currencyId: $this->currencyId,
            orderDate: '2026-09-07',
            deliveryDate: '2026-09-15',
            warehouseId: $this->warehouseId,
            items: [
                new SalesOrderItemDTO(
                    itemId: $this->itemId,
                    quantity: 5.0,
                    unitPrice: 40.0,
                    taxAmount: 10.0,
                ),
            ],
        );

        $order = $service->createSalesOrder($dto);

        $this->assertNotEmpty($order->sales_order_id);
        $this->assertSame(SalesOrderService::STATUS_DRAFT, (int) $order->status);
        $this->assertSame($this->tenantA->tenant_id, $order->tenant_id);
        $this->assertSame($this->warehouseId, $order->warehouse_id);
        $this->assertEquals(210.0, (float) $order->total_amount); // 5*40 + 10 tax
        $this->assertCount(1, $order->items);
    }

    #[Test]
    public function confirm_publishes_outbox_and_reserves_stock_via_listener(): void
    {
        $service = app(SalesOrderService::class);

        $dto = new CreateSalesOrderDTO(
            customerId: (string) Str::uuid(),
            currencyId: $this->currencyId,
            orderDate: '2026-09-07',
            deliveryDate: null,
            warehouseId: $this->warehouseId,
            items: [
                new SalesOrderItemDTO(
                    itemId: $this->itemId,
                    quantity: 7.0,
                    unitPrice: 20.0,
                ),
            ],
        );

        $order = $service->createSalesOrder($dto);
        $confirmed = $service->confirm($order->sales_order_id);

        $this->assertSame(SalesOrderService::STATUS_CONFIRMED, (int) $confirmed->status);

        $outbox = DB::table('event_outbox')
            ->where('aggregate_id', $order->sales_order_id)
            ->where('event_type', SalesOrderConfirmedV1::EVENT_TYPE)
            ->first();

        $this->assertNotNull($outbox);
        $payload = json_decode($outbox->payload, true);
        $this->assertSame($this->warehouseId, $payload['warehouse_id']);
        $this->assertCount(1, $payload['lines']);

        // Simulate ProcessOutboxMessageJob dispatch
        event(SalesOrderConfirmedV1::EVENT_TYPE, [$payload]);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(100.0, (float) $balance->quantity_on_hand);
        $this->assertEquals(7.0, (float) $balance->quantity_reserved);
    }

    #[Test]
    public function confirm_without_warehouse_fails(): void
    {
        $service = app(SalesOrderService::class);

        $dto = new CreateSalesOrderDTO(
            customerId: (string) Str::uuid(),
            currencyId: $this->currencyId,
            orderDate: '2026-09-07',
            deliveryDate: null,
            warehouseId: null,
            items: [
                new SalesOrderItemDTO(
                    itemId: $this->itemId,
                    quantity: 1.0,
                    unitPrice: 10.0,
                ),
            ],
        );

        $order = $service->createSalesOrder($dto);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
        $service->confirm($order->sales_order_id);
    }

    #[Test]
    public function reservation_service_increases_quantity_reserved(): void
    {
        $payload = [
            'event_type'     => SalesOrderConfirmedV1::EVENT_TYPE,
            'tenant_id'      => $this->tenantA->tenant_id,
            'sales_order_id' => (string) Str::uuid(),
            'order_number'   => 'SO-TEST',
            'customer_id'    => (string) Str::uuid(),
            'warehouse_id'   => $this->warehouseId,
            'lines'          => [
                [
                    'item_id'     => $this->itemId,
                    'quantity'    => '3.0000',
                    'line_number' => 1,
                ],
            ],
        ];

        $svc = app(SalesOrderStockReservationService::class);
        $balances = $svc->reserveFromConfirmedOrder($payload);

        $this->assertCount(1, $balances);
        $this->assertEquals(3.0, (float) $balances[0]->quantity_reserved);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();

        $this->assertEquals(3.0, (float) $balance->quantity_reserved);
        $this->assertEquals(100.0, (float) $balance->quantity_on_hand);
    }
}
