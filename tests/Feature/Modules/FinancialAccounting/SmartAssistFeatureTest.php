<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\AccountSuggestionService;
use App\Modules\FinancialAccounting\Application\Services\AnomalyAmountService;
use App\Modules\FinancialAccounting\Application\Services\JournalEntryService;
use App\Modules\FinancialAccounting\Application\Services\NaturalLanguageDraftService;
use App\Modules\FinancialAccounting\Application\Services\PlInsightService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\FinancialAccounting\Infrastructure\Models\SmartActionLog;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmartAssistFeatureTest extends TestCase
{
    protected Tenant $tenant;

    protected string $companyId;

    protected string $periodId;

    protected Ledger $ledger;

    protected Account $cash;

    protected Account $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_P6']);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenant->tenant_id]);

        $this->companyId = (string) Str::uuid();
        $this->periodId = (string) Str::uuid();

        $this->ledger = Ledger::create([
            'ledger_id'   => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'company_id'  => $this->companyId,
            'code'        => 'L1',
            'name'        => 'Leading',
            'is_leading'  => true,
            'status'      => 1,
            'row_version' => 1,
        ]);

        $this->cash = Account::create([
            'account_id'     => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'account_code'   => '1101',
            'name'           => 'Cash',
            'account_type'   => Account::TYPE_ASSET,
            'is_postable'    => true,
            'normal_balance' => Account::BALANCE_DEBIT,
            'row_version'    => 1,
        ]);

        $this->expense = Account::create([
            'account_id'     => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'account_code'   => '5101',
            'name'           => 'Expense',
            'account_type'   => Account::TYPE_EXPENSE,
            'is_postable'    => true,
            'normal_balance' => Account::BALANCE_DEBIT,
            'row_version'    => 1,
        ]);
    }

    #[Test]
    public function k2_suggests_and_decision_is_audited(): void
    {
        $journals = new JournalEntryService();
        // seed one posted journal for history
        $draft = $journals->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'lines'         => [
                ['account_id' => $this->expense->account_id, 'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => $this->cash->account_id, 'debit_amount' => 0, 'credit_amount' => 100],
            ],
        ]);
        // open period implicitly by posting without period control row (service may allow)
        try {
            $journals->post($draft->journal_entry_id, (string) Str::uuid());
        } catch (\Throwable) {
            // if period blocks, history empty is ok — still suggestions from fallback
        }

        $svc = new AccountSuggestionService();
        $result = $svc->suggest($this->companyId, 'هزینه', 1, 'actor-1');
        $this->assertTrue($result['override_allowed']);
        $this->assertNotEmpty($result['suggestions']);

        $svc->recordDecision(
            'OVERRIDDEN',
            $result['suggestions'][0]['account_id'],
            $this->cash->account_id,
            'actor-1',
            ['note' => 'test override']
        );

        $this->assertTrue(
            SmartActionLog::query()->where('feature_code', 'K2')->where('decision', 'OVERRIDDEN')->exists()
        );
    }

    #[Test]
    public function k6_nl_creates_draft_not_posted(): void
    {
        $svc = new NaturalLanguageDraftService();
        $result = $svc->createDraftFromCommand([
            'company_id' => $this->companyId,
            'ledger_id'  => $this->ledger->ledger_id,
            'period_id'  => $this->periodId,
            'command'    => 'بدهکار 5101 بستانکار 1101 مبلغ 250',
            'actor_id'   => 'actor-nl',
        ]);

        $this->assertSame(JournalEntry::STATUS_DRAFT, $result['status']);
        $je = JournalEntry::find($result['journal_entry_id']);
        $this->assertNotNull($je);
        $this->assertSame(JournalEntry::STATUS_DRAFT, $je->status);
        $this->assertSame('NL_ASSISTANT', $je->source_document_type);
        $this->assertTrue(
            SmartActionLog::query()->where('feature_code', 'K6')->exists()
        );
    }

    #[Test]
    public function k5_insights_non_blocking(): void
    {
        $svc = new PlInsightService();
        $result = $svc->insights($this->companyId, $this->periodId);
        $this->assertArrayHasKey('insights', $result);
        $this->assertArrayHasKey('pl', $result);
        $this->assertIsArray($result['insights']);
    }

    #[Test]
    public function anomaly_scan_returns_array(): void
    {
        $journals = new JournalEntryService();
        $draft = $journals->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'lines'         => [
                ['account_id' => $this->expense->account_id, 'debit_amount' => 10, 'credit_amount' => 0],
                ['account_id' => $this->cash->account_id, 'debit_amount' => 0, 'credit_amount' => 10],
            ],
        ]);

        $alerts = (new AnomalyAmountService())->scanJournal($draft->journal_entry_id);
        $this->assertIsArray($alerts);
    }
}
