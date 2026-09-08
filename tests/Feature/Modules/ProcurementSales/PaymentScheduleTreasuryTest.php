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
use App\Modules\ProcurementSales\Services\SalesInvoiceService;
use App\Modules\ProcurementSales\Services\PaymentSettlementService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class PaymentScheduleTreasuryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $customerId;
    protected string $currencyId;
    protected string $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['tenant_code' => 'TR_A', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        Context::add('tenant_id', $this->tenant->tenant_id);
        Context::add('user_id', $this->user->user_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
        ScopeContext::resetInstance();

        $this->currencyId = (string) Str::uuid();
        DB::table('currencies')->insert([
            'currency_id' => $this->currencyId, 'code' => 'IRR', 'name' => 'Rial', 'symbol' => 'R',
            'is_default' => false, 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $c = BusinessPartner::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->tenant_id, 'code' => 'C1', 'display_name' => 'C',
            'partner_type' => 2, 'status' => 1, 'credit_limit' => 0,
            'created_by' => $this->user->user_id, 'row_version' => 1,
        ]);
        $this->customerId = $c->business_partner_id;
        $this->itemId = (string) Str::uuid();
        FiscalPeriod::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->tenant_id, 'name' => 'FY',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(), 'is_closed' => false,
            'created_by' => $this->user->user_id, 'row_version' => 1,
        ]);
        foreach ([['1100','AR',1],['4000','Rev',4],['1000','Bank',1]] as [$code,$name,$type]) {
            DB::table('fin_accounts')->insert([
                'account_id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->tenant_id,
                'code' => $code, 'name' => $name, 'account_type' => $type, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ]);
        }
    }

    #[Test]
    public function schedule_carries_currency_and_mark_overdue_works(): void
    {
        $si = app(SalesInvoiceService::class);
        $settle = app(PaymentSettlementService::class);
        $inv = $si->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId, currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(), dueDate: now()->subDays(3)->toDateString(),
            salesOrderId: null, taxInvoiceNumber: null, description: null,
            items: [new SalesInvoiceItemDTO(itemId: $this->itemId, quantity: 1, unitPrice: 100, taxAmount: 0)],
        ));
        $posted = $si->post($inv->sales_invoice_id);
        $schedule = PaymentSchedule::query()->where('source_document_id', $posted->sales_invoice_id)->firstOrFail();
        $this->assertSame($this->currencyId, $schedule->currency_id);

        $n = $settle->markOverdueSchedules(now()->toDateString());
        $this->assertGreaterThanOrEqual(1, $n);
        $schedule->refresh();
        $this->assertSame(PaymentSchedule::STATUS_OVERDUE, (int) $schedule->status);
    }

    #[Test]
    public function can_split_into_installments(): void
    {
        $si = app(SalesInvoiceService::class);
        $settle = app(PaymentSettlementService::class);
        $inv = $si->create(new CreateSalesInvoiceDTO(
            customerId: $this->customerId, currencyId: $this->currencyId,
            invoiceDate: now()->toDateString(), dueDate: now()->addDays(30)->toDateString(),
            salesOrderId: null, taxInvoiceNumber: null, description: null,
            items: [new SalesInvoiceItemDTO(itemId: $this->itemId, quantity: 1, unitPrice: 100, taxAmount: 0)],
        ));
        $posted = $si->post($inv->sales_invoice_id);
        $rows = $settle->createInstallmentSchedulesForSalesInvoice($posted->sales_invoice_id, [
            ['due_date' => now()->addDays(10)->toDateString(), 'amount' => 40],
            ['due_date' => now()->addDays(20)->toDateString(), 'amount' => 60],
        ]);
        $this->assertCount(2, $rows);
        $this->assertEquals(2, PaymentSchedule::query()
            ->where('source_document_id', $posted->sales_invoice_id)->count());
    }
}
