<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\AccountDeterminationService;
use App\Modules\FinancialAccounting\Application\Services\SuggestedJournalService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\FinancialAccounting\Infrastructure\Models\SmartActionLog;
use App\Modules\FinancialAccounting\Infrastructure\Models\SuggestedJournal;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SuggestedJournalFeatureTest extends TestCase
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

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_P3']);
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
            'event_type'  => 'SALES_INVOICE_POSTED',
            'line_role'   => 'RECEIVABLE',
            'account_id'  => $this->ar->account_id,
            'description' => 'حساب دریافتنی فروش',
            'priority'    => 10,
        ]);
        $det->upsertRule([
            'event_type'  => 'SALES_INVOICE_POSTED',
            'line_role'   => 'REVENUE',
            'account_id'  => $this->revenue->account_id,
            'description' => 'درآمد فروش کالا',
            'priority'    => 10,
        ]);
    }

    #[Test]
    public function suggest_includes_per_line_reason_and_accept_creates_draft_not_posted(): void
    {
        $svc = new SuggestedJournalService();
        $docId = (string) Str::uuid();

        $sug = $svc->suggestFromOperational([
            'company_id'         => $this->companyId,
            'ledger_id'          => $this->ledger->ledger_id,
            'period_id'          => $this->periodId,
            'source_event_type'  => 'SALES_INVOICE_POSTED',
            'source_document_id' => $docId,
            'amounts'            => [
                'RECEIVABLE' => 1100,
                'REVENUE'    => 1100,
            ],
        ]);

        $this->assertSame(SuggestedJournal::STATUS_PENDING, $sug->status);
        $this->assertCount(2, $sug->lines);
        foreach ($sug->lines as $line) {
            $this->assertNotEmpty($line->suggestion_reason);
        }

        $accepted = $svc->accept($sug->suggested_journal_id, (string) Str::uuid(), 'تأیید حسابدار');
        $this->assertSame(SuggestedJournal::STATUS_ACCEPTED, $accepted->status);
        $this->assertNotNull($accepted->journal_entry_id);

        $je = JournalEntry::where('journal_entry_id', $accepted->journal_entry_id)->first();
        $this->assertNotNull($je);
        $this->assertSame(JournalEntry::STATUS_DRAFT, $je->status);

        $this->assertTrue(
            SmartActionLog::query()->where('action_type', 'ACCEPT')->exists()
        );
    }

    #[Test]
    public function reject_leaves_no_journal_entry(): void
    {
        $svc = new SuggestedJournalService();

        $sug = $svc->suggestFromOperational([
            'company_id'         => $this->companyId,
            'ledger_id'          => $this->ledger->ledger_id,
            'period_id'          => $this->periodId,
            'source_event_type'  => 'SALES_INVOICE_POSTED',
            'source_document_id' => (string) Str::uuid(),
            'amounts'            => [
                'RECEIVABLE' => 500,
                'REVENUE'    => 500,
            ],
        ]);

        $rejected = $svc->reject($sug->suggested_journal_id, null, 'حساب اشتباه');
        $this->assertSame(SuggestedJournal::STATUS_REJECTED, $rejected->status);
        $this->assertNull($rejected->journal_entry_id);
        $this->assertTrue(SmartActionLog::query()->where('action_type', 'REJECT')->exists());
    }
}
