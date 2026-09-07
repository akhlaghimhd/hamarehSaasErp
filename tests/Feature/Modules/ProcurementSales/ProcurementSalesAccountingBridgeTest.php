<?php

namespace Tests\Feature\Modules\ProcurementSales;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Services\InventoryDocumentService;
use App\Modules\Inventory\Services\PurchaseReceiptGoodsReceiptService;
use App\Modules\Accounting\Models\FiscalPeriod;
use App\Modules\Accounting\Services\FiscalPeriodService;
use App\Modules\Accounting\Services\VoucherPostingService;
use App\Modules\ProcurementSales\Services\ProcurementSalesAccountingService;
use App\Modules\ProcurementSales\Models\PurchaseReceipt;
use App\Modules\ProcurementSales\Events\PurchaseReceiptPostedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-PS-06 — Formal Procurement & Sales accounting bridge.
 *
 * Verifies:
 *  - Fiscal period resolution from open Accounting period (no random UUID when period exists).
 *  - Purchase Receipt → Goods Receipt still posts Inventory valuation voucher.
 *  - ProcurementSalesAccountingService is callable and posts commercial clearing when accounts exist.
 *  - Tenant isolation on period resolution.
 */
class ProcurementSalesAccountingBridgeTest extends TestCase
{
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $itemId;
    protected string $warehouseId;
    protected string $locationId;
    protected string $periodIdA;
    protected string $accountAsset;
    protected string $accountClearing;
    protected string $accountAp;
    protected string $accountAr;
    protected string $accountRevenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'PS_ACC_A',
            'status'      => 1,
        ]);
        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'PS_ACC_B',
            'status'      => 1,
        ]);
        $this->userA = User::factory()->create(['status' => 1]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        Context::add('tenant_id', $this->tenantA->tenant_id);
        Context::add('user_id', $this->userA->user_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        // Open fiscal period covering today for tenant A
        $this->periodIdA = (string) Str::uuid();
        FiscalPeriod::withoutGlobalScopes()->create([
            'period_id'   => $this->periodIdA,
            'tenant_id'   => $this->tenantA->tenant_id,
            'name'        => 'FY-2026-Q3',
            'start_date'  => now()->subMonths(1)->toDateString(),
            'end_date'    => now()->addMonths(2)->toDateString(),
            'is_closed'   => false,
            'created_by'  => $this->userA->user_id,
            'row_version' => 1,
        ]);

        // COA for tenant A
        $this->accountAsset    = (string) Str::uuid();
        $this->accountClearing = (string) Str::uuid();
        $this->accountAp       = (string) Str::uuid();
        $this->accountAr       = (string) Str::uuid();
        $this->accountRevenue  = (string) Str::uuid();

        DB::table('fin_accounts')->insert([
            [
                'account_id' => $this->accountAsset, 'tenant_id' => $this->tenantA->tenant_id,
                'code' => '1200', 'name' => 'Inventory Asset', 'account_type' => 1, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
            [
                'account_id' => $this->accountClearing, 'tenant_id' => $this->tenantA->tenant_id,
                'code' => '2100', 'name' => 'GR/IR Clearing', 'account_type' => 2, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
            [
                'account_id' => $this->accountAp, 'tenant_id' => $this->tenantA->tenant_id,
                'code' => '2000', 'name' => 'Accounts Payable', 'account_type' => 2, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
            [
                'account_id' => $this->accountAr, 'tenant_id' => $this->tenantA->tenant_id,
                'code' => '1100', 'name' => 'Accounts Receivable', 'account_type' => 1, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
            [
                'account_id' => $this->accountRevenue, 'tenant_id' => $this->tenantA->tenant_id,
                'code' => '4000', 'name' => 'Sales Revenue', 'account_type' => 4, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
        ]);

        $item = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITEM-PSACC-01',
            'name'             => 'PS Acc Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemId = $item->item_id;

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-PSACC',
            'name'         => 'PS Acc WH',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseId = $warehouse->warehouse_id;

        $loc = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-PSACC',
            'name'         => 'Bin PS Acc',
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
    public function fiscal_period_service_resolves_open_period_for_date(): void
    {
        $service = app(FiscalPeriodService::class);

        $resolved = $service->resolveOpenPeriodIdForDate(now()->toDateString());
        $this->assertSame($this->periodIdA, $resolved);

        // Closed period must not be returned
        FiscalPeriod::withoutGlobalScopes()
            ->where('period_id', $this->periodIdA)
            ->update(['is_closed' => true]);

        $this->assertNull($service->resolveOpenPeriodIdForDate(now()->toDateString()));
    }

    #[Test]
    public function purchase_receipt_goods_receipt_uses_real_fiscal_period(): void
    {
        $receiptId = (string) Str::uuid();
        $payload = [
            'event_type'          => PurchaseReceiptPostedV1::EVENT_TYPE,
            'tenant_id'           => $this->tenantA->tenant_id,
            'purchase_receipt_id' => $receiptId,
            'receipt_number'      => 'PR-TEST-001',
            'supplier_id'         => (string) Str::uuid(),
            'purchase_order_id'   => (string) Str::uuid(),
            'receipt_date'        => now()->toIso8601String(),
            'warehouse_id'        => $this->warehouseId,
            'posted_by'           => $this->userA->user_id,
            'lines'               => [
                [
                    'item_id'     => $this->itemId,
                    'quantity'    => '10',
                    'unit_price'  => '25.50',
                    'line_number' => 1,
                ],
            ],
        ];

        $service = app(PurchaseReceiptGoodsReceiptService::class);
        $document = $service->createFromPostedReceipt($payload);

        $this->assertSame(InventoryDocumentService::STATUS_POSTED, (int) $document->status);
        $this->assertSame($this->periodIdA, $document->fiscal_period_id);
        $this->assertSame('PUR_RECEIPT', $document->source_document_type);
        $this->assertSame($receiptId, $document->source_document_id);

        // Valuation voucher should have been posted by InventoryAccountingService
        $this->assertNotEmpty($document->accounting_voucher_id);

        $voucher = DB::table('fin_vouchers')
            ->where('voucher_id', $document->accounting_voucher_id)
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->first();
        $this->assertNotNull($voucher);
        $this->assertEqualsWithDelta(255.0, (float) $voucher->total_amount, 0.01);
    }

    #[Test]
    public function ps_accounting_service_posts_purchase_invoice_clearing(): void
    {
        $psAccounting = app(ProcurementSalesAccountingService::class);

        $voucherId = $psAccounting->postPurchaseInvoiceClearing(
            [
                'reference_number'   => 'PINV-TEST-001',
                'description'        => 'Test AP clearing',
                'source_document_id' => (string) Str::uuid(),
            ],
            1500.75,
            $this->tenantA->tenant_id
        );

        $this->assertNotNull($voucherId);

        $voucher = DB::table('fin_vouchers')->where('voucher_id', $voucherId)->first();
        $this->assertNotNull($voucher);
        $this->assertEqualsWithDelta(1500.75, (float) $voucher->total_amount, 0.01);

        $items = DB::table('fin_voucher_items')
            ->where('voucher_id', $voucherId)
            ->get();
        $this->assertCount(2, $items);

        $debits = $items->sum(fn ($r) => (float) $r->debit);
        $credits = $items->sum(fn ($r) => (float) $r->credit);
        $this->assertEqualsWithDelta($debits, $credits, 0.01);
    }

    #[Test]
    public function ps_accounting_service_posts_sales_invoice(): void
    {
        $psAccounting = app(ProcurementSalesAccountingService::class);

        $voucherId = $psAccounting->postSalesInvoice(
            [
                'reference_number'   => 'SINV-TEST-001',
                'description'        => 'Test AR/Revenue',
                'source_document_id' => (string) Str::uuid(),
            ],
            999.25,
            $this->tenantA->tenant_id
        );

        $this->assertNotNull($voucherId);

        $voucher = DB::table('fin_vouchers')->where('voucher_id', $voucherId)->first();
        $this->assertNotNull($voucher);
        $this->assertEqualsWithDelta(999.25, (float) $voucher->total_amount, 0.01);
    }

    #[Test]
    public function ps_accounting_service_skips_when_accounts_missing(): void
    {
        // Switch to tenant B which has no COA accounts
        TenantContext::getInstance()->setTenantId($this->tenantB->tenant_id);
        Context::add('tenant_id', $this->tenantB->tenant_id);
        app()->instance('current_tenant_id', $this->tenantB->tenant_id);

        $psAccounting = app(ProcurementSalesAccountingService::class);

        $voucherId = $psAccounting->postPurchaseInvoiceClearing(
            ['reference_number' => 'PINV-B-001'],
            100.0,
            $this->tenantB->tenant_id
        );

        $this->assertNull($voucherId);
    }

    #[Test]
    public function ps_accounting_service_resolve_fiscal_period_id_delegates(): void
    {
        $psAccounting = app(ProcurementSalesAccountingService::class);

        $resolved = $psAccounting->resolveFiscalPeriodId(now()->toDateString());
        $this->assertSame($this->periodIdA, $resolved);
    }
}
