<?php

namespace Tests\Feature\Modules\Inventory;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Services\PurchaseReceiptGoodsReceiptService;
use App\Modules\Inventory\Services\InventoryDocumentService;
use App\Modules\Inventory\Listeners\PurchaseReceiptPostedListener;
use App\Modules\ProcurementSales\Events\PurchaseReceiptPostedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-PS-04 — Purchase Receipt posted → Inventory Goods Receipt (boundary, no FK)
 */
class PurchaseReceiptToGoodsReceiptTest extends TestCase
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
            'tenant_code' => 'PS_GR_A',
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
            'code'             => 'ITEM-GR-01',
            'name'             => 'GR Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemId = $item->item_id;

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-GR',
            'name'         => 'GR Warehouse',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseId = $warehouse->warehouse_id;

        $loc = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-GR-1',
            'name'         => 'Bin GR 1',
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

    protected function samplePayload(?string $receiptId = null): array
    {
        $receiptId = $receiptId ?? (string) Str::uuid();

        return [
            'event_type'          => PurchaseReceiptPostedV1::EVENT_TYPE,
            'tenant_id'           => $this->tenantA->tenant_id,
            'purchase_receipt_id' => $receiptId,
            'receipt_number'      => 'PR-TEST-001',
            'supplier_id'         => (string) Str::uuid(),
            'purchase_order_id'   => (string) Str::uuid(),
            'receipt_date'        => '2026-09-05',
            'warehouse_id'        => $this->warehouseId,
            'lines'               => [
                [
                    'item_id'     => $this->itemId,
                    'quantity'    => '12.0000',
                    'unit_price'  => '25.5000',
                    'line_number' => 1,
                ],
            ],
        ];
    }

    #[Test]
    public function creates_draft_goods_receipt_document_from_posted_purchase_receipt_payload(): void
    {
        $service = app(PurchaseReceiptGoodsReceiptService::class);
        $receiptId = (string) Str::uuid();
        $payload = $this->samplePayload($receiptId);

        $document = $service->createFromPostedReceipt($payload);

        $this->assertNotEmpty($document->document_id);
        $this->assertSame(InventoryDocumentService::TYPE_RECEIPT, (int) $document->document_type);
        $this->assertSame(InventoryDocumentService::STATUS_DRAFT, (int) $document->status);
        $this->assertSame(PurchaseReceiptGoodsReceiptService::SOURCE_TYPE, $document->source_document_type);
        $this->assertSame($receiptId, $document->source_document_id);
        $this->assertCount(1, $document->items);
        $this->assertSame($this->itemId, $document->items->first()->item_id);
        $this->assertEquals(12.0, (float) $document->items->first()->quantity);
        $this->assertSame($this->locationId, $document->items->first()->to_location_id);
    }

    #[Test]
    public function is_idempotent_for_same_purchase_receipt(): void
    {
        $service = app(PurchaseReceiptGoodsReceiptService::class);
        $receiptId = (string) Str::uuid();
        $payload = $this->samplePayload($receiptId);

        $first = $service->createFromPostedReceipt($payload);
        $second = $service->createFromPostedReceipt($payload);

        $this->assertSame($first->document_id, $second->document_id);
        $this->assertSame(
            1,
            InventoryDocument::query()
                ->where('source_document_type', PurchaseReceiptGoodsReceiptService::SOURCE_TYPE)
                ->where('source_document_id', $receiptId)
                ->count()
        );
    }

    #[Test]
    public function listener_handles_string_event_from_outbox_pipeline(): void
    {
        $receiptId = (string) Str::uuid();
        $payload = $this->samplePayload($receiptId);

        // Same dispatch path as ProcessOutboxMessageJob: event($eventName, [$payload])
        event(PurchaseReceiptPostedV1::EVENT_TYPE, [$payload]);

        $doc = InventoryDocument::query()
            ->where('source_document_type', PurchaseReceiptGoodsReceiptService::SOURCE_TYPE)
            ->where('source_document_id', $receiptId)
            ->with('items')
            ->first();

        $this->assertNotNull($doc);
        $this->assertCount(1, $doc->items);
        $this->assertSame($this->locationId, $doc->items->first()->to_location_id);
    }
}
