<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\AccountDeterminationService;
use App\Modules\FinancialAccounting\Application\Services\FinanceEventPublisher;
use App\Modules\FinancialAccounting\Application\Services\FiscalPeriodControlService;
use App\Modules\FinancialAccounting\Application\Services\JournalEntryService;
use App\Modules\FinancialAccounting\Application\Services\OperationalEventConsumer;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\FinancialAccounting\Infrastructure\Models\PeriodControl;
use App\Modules\FinancialAccounting\Infrastructure\Models\SuggestedJournal;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OperationalConsumerAndOutboxTest extends TestCase
{
    protected Tenant $tenant;

    protected string $companyId;

    protected string $periodId;

    protected Ledger $ledger;

    protected Account $ar;

    protected Account $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_P3C']);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);

        $this->companyId = (string) Str::uuid();
        $this->periodId = (string) Str::uuid();

        $this->ledger = Ledger::create([
            'ledger_id'   => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'company_id'  => $this->companyId,
            'code'        => 'LG',
            'name'        => 'Leading',
            'is_leading'  => true,
            'status'      => 1,
            'row_version' => 1,
        ]);

        $this->ar = Account::create([
            'account_id'     => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'account_code'   => '1103',
            'name'           => 'دریافتنی',
            'account_type'   => Account::TYPE_ASSET,
            'is_postable'    => true,
            'normal_balance' => Account::BALANCE_DEBIT,
            'row_version'    => 1,
        ]);

        $this->revenue = Account::create([
            'account_id'     => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'account_code'   => '4101',
            'name'           => 'فروش',
            'account_type'   => Account::TYPE_REVENUE,
            'is_postable'    => true,
            'normal_balance' => Account::BALANCE_CREDIT,
            'row_version'    => 1,
        ]);

        $det = new AccountDeterminationService();
        $det->upsertRule([
            'event_type' => 'SALES_INVOICE_POSTED',
            'line_role'  => 'RECEIVABLE',
            'account_id' => $this->ar->account_id,
            'priority'   => 10,
        ]);
        $det->upsertRule([
            'event_type' => 'SALES_INVOICE_POSTED',
            'line_role'  => 'REVENUE',
            'account_id' => $this->revenue->account_id,
            'priority'   => 10,
        ]);

        PeriodControl::create([
            'period_control_id' => (string) Str::uuid(),
            'tenant_id'         => $this->tenant->tenant_id,
            'company_id'        => $this->companyId,
            'period_id'         => $this->periodId,
            'status'            => PeriodControl::STATUS_OPEN,
            'row_version'       => 1,
        ]);
    }

    #[Test]
    public function consumer_creates_pending_suggestion_not_posted_journal(): void
    {
        $consumer = new OperationalEventConsumer();
        $docId = (string) Str::uuid();

        $sug = $consumer->consume(OperationalEventConsumer::SALES_INVOICE_POSTED, [
            'tenant_id'    => $this->tenant->tenant_id,
            'company_id'   => $this->companyId,
            'period_id'    => $this->periodId,
            'ledger_id'    => $this->ledger->ledger_id,
            'document_id'  => $docId,
            'net_amount'   => 1000,
            'tax_amount'   => 0,
            'gross_amount' => 1000,
        ]);

        $this->assertNotNull($sug);
        $this->assertSame(SuggestedJournal::STATUS_PENDING, $sug->status);
        $this->assertNull($sug->journal_entry_id);

        // idempotent second call
        $again = $consumer->consume(OperationalEventConsumer::SALES_INVOICE_POSTED, [
            'tenant_id'   => $this->tenant->tenant_id,
            'company_id'  => $this->companyId,
            'period_id'   => $this->periodId,
            'ledger_id'   => $this->ledger->ledger_id,
            'document_id' => $docId,
            'net_amount'  => 1000,
            'gross_amount'=> 1000,
        ]);
        $this->assertSame($sug->suggested_journal_id, $again->suggested_journal_id);
    }

    #[Test]
    public function post_writes_journal_posted_outbox_event(): void
    {
        if (! Schema::hasTable('event_outbox')) {
            $this->markTestSkipped('event_outbox table not present');
        }

        $svc = new JournalEntryService();
        $draft = $svc->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'lines'         => [
                ['account_id' => $this->ar->account_id, 'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => $this->revenue->account_id, 'debit_amount' => 0, 'credit_amount' => 100],
            ],
        ]);

        $posted = $svc->post($draft->journal_entry_id, (string) Str::uuid());
        $this->assertSame(JournalEntry::STATUS_POSTED, $posted->status);

        $row = DB::table('event_outbox')
            ->where('event_type', FinanceEventPublisher::JOURNAL_POSTED)
            ->where('aggregate_id', $posted->journal_entry_id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->status);
    }
}
