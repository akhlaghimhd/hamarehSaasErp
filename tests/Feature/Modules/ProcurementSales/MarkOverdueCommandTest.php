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
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class MarkOverdueCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function artisan_command_marks_overdue_schedules(): void
    {
        $tenant = Tenant::factory()->create(['tenant_code' => 'OV_CMD', 'status' => 1]);
        $user = User::factory()->create(['status' => 1]);
        TenantContext::getInstance()->setTenantId($tenant->tenant_id);
        Context::add('tenant_id', $tenant->tenant_id);
        Context::add('user_id', $user->user_id);
        app()->instance('current_tenant_id', $tenant->tenant_id);
        ScopeContext::resetInstance();

        $currencyId = (string) Str::uuid();
        DB::table('currencies')->insert([
            'currency_id' => $currencyId, 'code' => 'IRR', 'name' => 'Rial', 'symbol' => 'R',
            'is_default' => false, 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $c = BusinessPartner::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->tenant_id, 'code' => 'C1', 'display_name' => 'C',
            'partner_type' => 2, 'status' => 1, 'credit_limit' => 0,
            'created_by' => $user->user_id, 'row_version' => 1,
        ]);
        FiscalPeriod::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->tenant_id, 'name' => 'FY',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(), 'is_closed' => false,
            'created_by' => $user->user_id, 'row_version' => 1,
        ]);
        foreach ([['1100','AR',1],['4000','Rev',4],['1000','Bank',1]] as [$code,$name,$type]) {
            DB::table('fin_accounts')->insert([
                'account_id' => (string) Str::uuid(), 'tenant_id' => $tenant->tenant_id,
                'code' => $code, 'name' => $name, 'account_type' => $type, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ]);
        }

        $si = app(SalesInvoiceService::class);
        $inv = $si->create(new CreateSalesInvoiceDTO(
            customerId: $c->business_partner_id, currencyId: $currencyId,
            invoiceDate: now()->toDateString(), dueDate: now()->subDays(2)->toDateString(),
            salesOrderId: null, taxInvoiceNumber: null, description: null,
            items: [new SalesInvoiceItemDTO(itemId: (string) Str::uuid(), quantity: 1, unitPrice: 50, taxAmount: 0)],
        ));
        $si->post($inv->sales_invoice_id);

        $this->artisan('erp:mark-payment-schedules-overdue')->assertSuccessful();

        $schedule = PaymentSchedule::query()->where('source_document_id', $inv->sales_invoice_id)->firstOrFail();
        $this->assertSame(PaymentSchedule::STATUS_OVERDUE, (int) $schedule->status);
    }
}
