<?php

namespace Tests\Feature\Modules\ProcurementSales;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\MasterData\Models\BusinessPartner;
use App\Modules\Accounting\Models\FiscalPeriod;
use App\Modules\ProcurementSales\DTOs\CreateSalesInvoiceDTO;
use App\Modules\ProcurementSales\DTOs\SalesInvoiceItemDTO;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseInvoiceDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseInvoiceItemDTO;
use App\Modules\ProcurementSales\Services\SalesInvoiceService;
use App\Modules\ProcurementSales\Services\PurchaseInvoiceService;
use App\Modules\ProcurementSales\Services\CreditLimitService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * L6-PS-07 — Sales/Purchase Invoice + Credit Limit + AR/AP voucher bridge.
 */
class SalesPurchaseInvoiceAndCreditLimitTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $customerId;
    protected string $supplierId;
    protected string $currencyId;
    protected string $itemId;
    protected string $accountAr;
    protected string $accountRevenue;
    protected string $accountAp;
    protected string $accountClearing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'PS_INV_A',
            'status'      => 1,
        ]);
        $this->user = User::factory()->create(['status' => 1]);

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        Context::add('tenant_id', $this->tenant->tenant_id);
        Context::add('user_id', $this->user->user_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
        ScopeContext::resetInstance();

        $this->currencyId = (string) Str::uuid();
        DB::table('currencies')->insert([
            'currency_id' => $this->currencyId,
            'code'        => 'IRR',
            'name'        => 'Rial',
            'symbol'      => 'R',
            'is_default'  => false,
            'status'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $customer = BusinessPartner::withoutGlobalScopes()->create([
            'tenant_id'     => $this->tenant->tenant_id,
            'code'          => 'CUST-01',
            'display_name'  => 'Customer One',
            'partner_type'  => 2,
            'status'        => 1,
            'credit_limit'  => 1000.0000,
            'created_by'    => $this->user->user_id,
            'row_version'   => 1,
        ]);
        $this->customerId = $customer->business_partner_id;

        $supplier = BusinessPartner::withoutGlobalScopes()->create([
            'tenant_id'     => $this->tenant->tenant_id,
            'code'          => 'SUP-01',
            'display_name'  => 'Supplier One',
            'partner_type'  => 2,
            'status'        => 1,
            'credit_limit'  => 0,
            'created_by'    => $this->user->user_id,
            'row_version'   => 1,
        ]);
        $this->supplierId = $supplier->business_partner_id;

        $this->itemId = (string) Str::uuid();

        FiscalPeriod::withoutGlobalScopes()->create([
            'tenant_id'   => $this->tenant->tenant_id,
            'name'        => 'FY-2026-PS07',
            'start_date'  => now()->subDays(7)->toDateString(),
            'end_date'    => now()->addMonths(2)->toDateString(),
            'is_closed'   => false,
            'created_by'  => $this->user->user_id,
            'row_version' => 1,
        ]);

        $this->accountAr       = (string) Str::uuid();
        $this->accountRevenue  = (string) Str::uuid();
        $this->accountAp       = (string) Str::uuid();
        $this->accountClearing = (string) Str::uuid();

        DB::table('fin_accounts')->insert([
            [
                'account_id' => $this->accountAr, 'tenant_id' => $this->tenant->tenant_id,
                'code' => '1100', 'name' => 'Accounts Receivable', 'account_type' => 1, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
            [
                'account_id' => $this->accountRevenue, 'tenant_id' => $this->tenant->tenant_id,
                'code' => '4000', 'name' => 'Sales Revenue', 'account_type' => 4, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
            [
                'account_id' => $this->accountAp, 'tenant_id' => $this->tenant->tenant_id,
                'code' => '2000', 'name' => 'Accounts Payable', 'account_type' => 2, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
            [
                'account_id' => $this->accountClearing, 'tenant_id' => $this->tenant->tenant_id,
                'code' => '2100', 'name' => 'GR/IR Clearing', 'account_type' => 2, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
        ]);
    }

    #[Test]
    public function can_create_draft_sales_invoice_with_tax_and_discount(): void
    {
        $service = app(SalesInvoiceService::class);

        $invoice = $service->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId,
            currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(),
            dueDate: now()->addDays(30)->toDateString(),
            salesOrderId: null,
            taxInvoiceNumber: 'TAX-100',
            description: 'Test SI',
            items: [
                new SalesInvoiceItemDTO(
                    itemId: $this->itemId,
                    quantity: 10,
                    unitPrice: 50,
                    discountAmount: 20,
                    taxAmount: 45,
                    uomCode: 'EA',
                    lineNumber: 1,
                ),
            ],
        ));

        $this->assertNotNull($invoice->sales_invoice_id);
        $this->assertSame(SalesInvoiceService::STATUS_DRAFT, (int) $invoice->status);
        $this->assertEquals(480.0, (float) $invoice->subtotal_amount);
        $this->assertEquals(45.0, (float) $invoice->tax_amount);
        $this->assertEquals(525.0, (float) $invoice->total_amount);
        $this->assertCount(1, $invoice->items);
    }

    #[Test]
    public function posting_sales_invoice_creates_ar_voucher_and_opens_status(): void
    {
        $service = app(SalesInvoiceService::class);

        $invoice = $service->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId,
            currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(),
            dueDate: null,
            salesOrderId: null,
            taxInvoiceNumber: null,
            description: null,
            items: [
                new SalesInvoiceItemDTO(
                    itemId: $this->itemId,
                    quantity: 2,
                    unitPrice: 100,
                    discountAmount: 0,
                    taxAmount: 20,
                ),
            ],
        ));

        $posted = $service->post($invoice->sales_invoice_id);

        $this->assertSame(SalesInvoiceService::STATUS_OPEN, (int) $posted->status);
        $this->assertNotNull($posted->accounting_voucher_id);
        $this->assertNotNull($posted->posting_date);
        $this->assertNotNull($posted->fiscal_period_id);

        $amount = 220.0;
        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $posted->accounting_voucher_id,
            'account_id' => $this->accountAr,
            'debit'      => $amount,
        ]);
        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $posted->accounting_voucher_id,
            'account_id' => $this->accountRevenue,
            'credit'     => $amount,
        ]);
    }

    #[Test]
    public function credit_limit_blocks_post_when_projected_exceeds_limit(): void
    {
        $service = app(SalesInvoiceService::class);

        $first = $service->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId,
            currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(),
            dueDate: null,
            salesOrderId: null,
            taxInvoiceNumber: null,
            description: null,
            items: [
                new SalesInvoiceItemDTO(itemId: $this->itemId, quantity: 8, unitPrice: 100, taxAmount: 0),
            ],
        ));
        $service->post($first->sales_invoice_id);

        $second = $service->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId,
            currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(),
            dueDate: null,
            salesOrderId: null,
            taxInvoiceNumber: null,
            description: null,
            items: [
                new SalesInvoiceItemDTO(itemId: $this->itemId, quantity: 3, unitPrice: 100, taxAmount: 0),
            ],
        ));

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Credit limit exceeded');
        $service->post($second->sales_invoice_id);
    }

    #[Test]
    public function credit_limit_zero_means_unlimited(): void
    {
        BusinessPartner::withoutGlobalScopes()
            ->where('business_partner_id', $this->customerId)
            ->update(['credit_limit' => 0]);

        $service = app(SalesInvoiceService::class);
        $invoice = $service->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId,
            currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(),
            dueDate: null,
            salesOrderId: null,
            taxInvoiceNumber: null,
            description: null,
            items: [
                new SalesInvoiceItemDTO(itemId: $this->itemId, quantity: 50, unitPrice: 100, taxAmount: 0),
            ],
        ));

        $posted = $service->post($invoice->sales_invoice_id);
        $this->assertSame(SalesInvoiceService::STATUS_OPEN, (int) $posted->status);
    }

    #[Test]
    public function can_create_and_post_purchase_invoice_with_ap_clearing_voucher(): void
    {
        $service = app(PurchaseInvoiceService::class);

        $invoice = $service->create(new CreatePurchaseInvoiceDTO(
            supplierId: $this->supplierId,
            currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(),
            dueDate: now()->addDays(15)->toDateString(),
            purchaseOrderId: null,
            supplierInvoiceRef: 'SUP-INV-99',
            taxInvoiceNumber: null,
            description: 'Goods received invoice',
            items: [
                new PurchaseInvoiceItemDTO(
                    itemId: $this->itemId,
                    quantity: 5,
                    unitPrice: 40,
                    discountAmount: 10,
                    taxAmount: 19,
                ),
            ],
        ));

        $this->assertSame(PurchaseInvoiceService::STATUS_DRAFT, (int) $invoice->status);
        $this->assertEquals(190.0, (float) $invoice->subtotal_amount);
        $this->assertEquals(209.0, (float) $invoice->total_amount);

        $posted = $service->post($invoice->purchase_invoice_id);

        $this->assertSame(PurchaseInvoiceService::STATUS_OPEN, (int) $posted->status);
        $this->assertNotNull($posted->accounting_voucher_id);

        $amount = 209.0;
        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $posted->accounting_voucher_id,
            'account_id' => $this->accountClearing,
            'debit'      => $amount,
        ]);
        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $posted->accounting_voucher_id,
            'account_id' => $this->accountAp,
            'credit'     => $amount,
        ]);
    }

    #[Test]
    public function cannot_post_non_draft_sales_invoice(): void
    {
        $service = app(SalesInvoiceService::class);
        $invoice = $service->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId,
            currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(),
            dueDate: null,
            salesOrderId: null,
            taxInvoiceNumber: null,
            description: null,
            items: [
                new SalesInvoiceItemDTO(itemId: $this->itemId, quantity: 1, unitPrice: 10, taxAmount: 0),
            ],
        ));
        $service->post($invoice->sales_invoice_id);

        $this->expectException(ConflictHttpException::class);
        $service->post($invoice->sales_invoice_id);
    }

    #[Test]
    public function credit_limit_service_reports_outstanding(): void
    {
        $service = app(SalesInvoiceService::class);
        $credit = app(CreditLimitService::class);

        $this->assertEquals(0.0, $credit->getOutstandingReceivable($this->customerId));

        $inv = $service->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId,
            currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(),
            dueDate: null,
            salesOrderId: null,
            taxInvoiceNumber: null,
            description: null,
            items: [
                new SalesInvoiceItemDTO(itemId: $this->itemId, quantity: 4, unitPrice: 50, taxAmount: 0),
            ],
        ));
        $service->post($inv->sales_invoice_id);

        $this->assertEquals(200.0, $credit->getOutstandingReceivable($this->customerId));
    }
}
