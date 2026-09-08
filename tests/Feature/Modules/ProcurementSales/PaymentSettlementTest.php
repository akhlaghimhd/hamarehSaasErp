<?php

namespace Tests\Feature\Modules\ProcurementSales;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\MasterData\Models\BusinessPartner;
use App\Modules\Accounting\Models\FiscalPeriod;
use App\Modules\Accounting\Models\PaymentSchedule;
use App\Modules\ProcurementSales\DTOs\CreateSalesInvoiceDTO;
use App\Modules\ProcurementSales\DTOs\SalesInvoiceItemDTO;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseInvoiceDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseInvoiceItemDTO;
use App\Modules\ProcurementSales\Services\SalesInvoiceService;
use App\Modules\ProcurementSales\Services\PurchaseInvoiceService;
use App\Modules\ProcurementSales\Services\PaymentSettlementService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaymentSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $customerId;
    protected string $supplierId;
    protected string $currencyId;
    protected string $itemId;
    protected string $bankAccountId;
    protected string $accountAr;
    protected string $accountRevenue;
    protected string $accountAp;
    protected string $accountClearing;
    protected string $accountBank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'PS_PAY_A', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        Context::add('tenant_id', $this->tenant->tenant_id);
        Context::add('user_id', $this->user->user_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
        ScopeContext::resetInstance();

        $this->currencyId = (string) Str::uuid();
        DB::table('currencies')->insert([
            'currency_id' => $this->currencyId,
            'code' => 'IRR', 'name' => 'Rial', 'symbol' => 'R',
            'is_default' => false, 'status' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $customer = BusinessPartner::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->tenant_id, 'code' => 'CUST-PAY',
            'display_name' => 'Customer Pay', 'partner_type' => 2, 'status' => 1,
            'credit_limit' => 0, 'created_by' => $this->user->user_id, 'row_version' => 1,
        ]);
        $this->customerId = $customer->business_partner_id;

        $supplier = BusinessPartner::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->tenant_id, 'code' => 'SUP-PAY',
            'display_name' => 'Supplier Pay', 'partner_type' => 2, 'status' => 1,
            'credit_limit' => 0, 'created_by' => $this->user->user_id, 'row_version' => 1,
        ]);
        $this->supplierId = $supplier->business_partner_id;

        $this->itemId = (string) Str::uuid();
        $this->bankAccountId = (string) Str::uuid();

        FiscalPeriod::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->tenant_id, 'name' => 'FY-2026-PS08',
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'is_closed' => false, 'created_by' => $this->user->user_id, 'row_version' => 1,
        ]);

        $this->accountAr = (string) Str::uuid();
        $this->accountRevenue = (string) Str::uuid();
        $this->accountAp = (string) Str::uuid();
        $this->accountClearing = (string) Str::uuid();
        $this->accountBank = (string) Str::uuid();

        DB::table('fin_accounts')->insert([
            ['account_id' => $this->accountAr, 'tenant_id' => $this->tenant->tenant_id, 'code' => '1100', 'name' => 'AR', 'account_type' => 1, 'level' => 1, 'is_active' => true, 'created_at' => now(), 'row_version' => 1],
            ['account_id' => $this->accountRevenue, 'tenant_id' => $this->tenant->tenant_id, 'code' => '4000', 'name' => 'Revenue', 'account_type' => 4, 'level' => 1, 'is_active' => true, 'created_at' => now(), 'row_version' => 1],
            ['account_id' => $this->accountAp, 'tenant_id' => $this->tenant->tenant_id, 'code' => '2000', 'name' => 'AP', 'account_type' => 2, 'level' => 1, 'is_active' => true, 'created_at' => now(), 'row_version' => 1],
            ['account_id' => $this->accountClearing, 'tenant_id' => $this->tenant->tenant_id, 'code' => '2100', 'name' => 'GRIR', 'account_type' => 2, 'level' => 1, 'is_active' => true, 'created_at' => now(), 'row_version' => 1],
            ['account_id' => $this->accountBank, 'tenant_id' => $this->tenant->tenant_id, 'code' => '1000', 'name' => 'Bank', 'account_type' => 1, 'level' => 1, 'is_active' => true, 'created_at' => now(), 'row_version' => 1],
        ]);
    }

    #[Test]
    public function posting_sales_invoice_creates_payment_schedule(): void
    {
        $service = app(SalesInvoiceService::class);
        $invoice = $service->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId, currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(), dueDate: now()->addDays(10)->toDateString(),
            salesOrderId: null, taxInvoiceNumber: null, description: null,
            items: [new SalesInvoiceItemDTO(itemId: $this->itemId, quantity: 2, unitPrice: 100, taxAmount: 0)],
        ));
        $posted = $service->post($invoice->sales_invoice_id);

        $this->assertDatabaseHas('fin_payment_schedules', [
            'source_document_type' => PaymentSchedule::SOURCE_SAL_INVOICE,
            'source_document_id' => $posted->sales_invoice_id,
            'expected_amount' => 200.0,
            'paid_amount' => 0,
            'status' => PaymentSchedule::STATUS_PENDING,
        ]);
    }

    #[Test]
    public function partial_and_full_ar_receipt_updates_schedule_and_invoice(): void
    {
        $si = app(SalesInvoiceService::class);
        $settle = app(PaymentSettlementService::class);

        $invoice = $si->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId, currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(), dueDate: null,
            salesOrderId: null, taxInvoiceNumber: null, description: null,
            items: [new SalesInvoiceItemDTO(itemId: $this->itemId, quantity: 1, unitPrice: 100, taxAmount: 0)],
        ));
        $posted = $si->post($invoice->sales_invoice_id);
        $schedule = PaymentSchedule::query()->where('source_document_id', $posted->sales_invoice_id)->firstOrFail();

        $settle->recordReceipt($schedule->payment_schedule_id, $this->bankAccountId, 40.0, 'RCPT-1');
        $schedule->refresh();
        $this->assertEquals(40.0, (float) $schedule->paid_amount);
        $this->assertSame(PaymentSchedule::STATUS_PARTIALLY_PAID, (int) $schedule->status);
        $this->assertDatabaseHas('sales_invoices', [
            'sales_invoice_id' => $posted->sales_invoice_id,
            'status' => SalesInvoiceService::STATUS_PARTIALLY_PAID,
        ]);
        $this->assertDatabaseHas('fin_voucher_items', ['account_id' => $this->accountBank, 'debit' => 40.0]);
        $this->assertDatabaseHas('fin_voucher_items', ['account_id' => $this->accountAr, 'credit' => 40.0]);

        $settle->recordReceipt($schedule->payment_schedule_id, $this->bankAccountId, 60.0, 'RCPT-2');
        $schedule->refresh();
        $this->assertEquals(100.0, (float) $schedule->paid_amount);
        $this->assertSame(PaymentSchedule::STATUS_SETTLED, (int) $schedule->status);
        $this->assertDatabaseHas('sales_invoices', [
            'sales_invoice_id' => $posted->sales_invoice_id,
            'status' => SalesInvoiceService::STATUS_FULLY_PAID,
        ]);
    }

    #[Test]
    public function cannot_overpay_schedule(): void
    {
        $si = app(SalesInvoiceService::class);
        $settle = app(PaymentSettlementService::class);
        $invoice = $si->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId, currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(), dueDate: null,
            salesOrderId: null, taxInvoiceNumber: null, description: null,
            items: [new SalesInvoiceItemDTO(itemId: $this->itemId, quantity: 1, unitPrice: 50, taxAmount: 0)],
        ));
        $posted = $si->post($invoice->sales_invoice_id);
        $schedule = PaymentSchedule::query()->where('source_document_id', $posted->sales_invoice_id)->firstOrFail();

        $this->expectException(ConflictHttpException::class);
        $settle->recordReceipt($schedule->payment_schedule_id, $this->bankAccountId, 51.0);
    }

    #[Test]
    public function ap_payment_settles_purchase_invoice(): void
    {
        $pi = app(PurchaseInvoiceService::class);
        $settle = app(PaymentSettlementService::class);
        $invoice = $pi->create(new CreatePurchaseInvoiceDTO(
            supplierId: $this->supplierId, currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(), dueDate: now()->addDays(5)->toDateString(),
            purchaseOrderId: null, supplierInvoiceRef: 'SUP-1', taxInvoiceNumber: null, description: null,
            items: [new PurchaseInvoiceItemDTO(itemId: $this->itemId, quantity: 2, unitPrice: 30, taxAmount: 0)],
        ));
        $posted = $pi->post($invoice->purchase_invoice_id);
        $schedule = PaymentSchedule::query()
            ->where('source_document_type', PaymentSchedule::SOURCE_PUR_INVOICE)
            ->where('source_document_id', $posted->purchase_invoice_id)
            ->firstOrFail();
        $this->assertEquals(60.0, (float) $schedule->expected_amount);

        $settle->recordPayment($schedule->payment_schedule_id, $this->bankAccountId, 60.0, 'PAY-1');
        $schedule->refresh();
        $this->assertSame(PaymentSchedule::STATUS_SETTLED, (int) $schedule->status);
        $this->assertDatabaseHas('purchase_invoices', [
            'purchase_invoice_id' => $posted->purchase_invoice_id,
            'status' => PurchaseInvoiceService::STATUS_FULLY_PAID,
        ]);
        $this->assertDatabaseHas('fin_voucher_items', ['account_id' => $this->accountAp, 'debit' => 60.0]);
        $this->assertDatabaseHas('fin_voucher_items', ['account_id' => $this->accountBank, 'credit' => 60.0]);
    }
}
