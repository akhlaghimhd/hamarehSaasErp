<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\IntercompanyJournalService;
use App\Modules\FinancialAccounting\Application\Services\JournalEntryService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\IcJournalPair;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\IntercompanyPartner;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntercompanyFeatureTest extends TestCase
{
    protected Tenant $tenant;

    protected string $fromCompanyId;

    protected string $toCompanyId;

    protected string $elimCompanyId;

    protected string $periodId;

    protected Ledger $fromLedger;

    protected Ledger $toLedger;

    protected Ledger $elimLedger;

    protected Account $dueFrom;

    protected Account $dueTo;

    protected Account $offsetFrom;

    protected Account $offsetTo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_P5']);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);

        $this->periodId = (string) Str::uuid();

        $this->fromCompanyId = (string) Str::uuid();
        $this->toCompanyId = (string) Str::uuid();
        $this->elimCompanyId = (string) Str::uuid();

        Company::create([
            'company_id'  => $this->fromCompanyId,
            'tenant_id'   => $this->tenant->tenant_id,
            'code'        => 'C-FROM',
            'name'        => 'From Co',
            'legal_name'  => 'From Co',
            'entity_kind' => Company::ENTITY_KIND_OPERATING,
            'is_primary'  => true,
            'is_active'   => true,
            'status'      => 1,
            'row_version' => 1,
        ]);
        Company::create([
            'company_id'        => $this->toCompanyId,
            'tenant_id'         => $this->tenant->tenant_id,
            'code'              => 'C-TO',
            'name'              => 'To Co',
            'legal_name'        => 'To Co',
            'entity_kind'       => Company::ENTITY_KIND_OPERATING,
            'parent_company_id' => $this->fromCompanyId,
            'is_primary'        => false,
            'is_active'         => true,
            'status'            => 1,
            'row_version'       => 1,
        ]);
        Company::create([
            'company_id'        => $this->elimCompanyId,
            'tenant_id'         => $this->tenant->tenant_id,
            'code'              => 'C-ELIM',
            'name'              => 'Elim',
            'legal_name'        => 'Elim',
            'entity_kind'       => Company::ENTITY_KIND_ELIMINATION,
            'parent_company_id' => $this->fromCompanyId,
            'is_primary'        => false,
            'is_active'         => true,
            'status'            => 1,
            'row_version'       => 1,
        ]);

        IntercompanyPartner::create([
            'ic_partner_id'    => (string) Str::uuid(),
            'tenant_id'        => $this->tenant->tenant_id,
            'from_company_id'  => $this->fromCompanyId,
            'to_company_id'    => $this->toCompanyId,
            'is_active'        => true,
            'row_version'      => 1,
        ]);

        $this->fromLedger = $this->makeLedger($this->fromCompanyId, 'LF');
        $this->toLedger = $this->makeLedger($this->toCompanyId, 'LT');
        $this->elimLedger = $this->makeLedger($this->elimCompanyId, 'LE');

        $this->dueFrom = $this->makeAccount('1301', 'Due from IC', Account::TYPE_ASSET);
        $this->dueTo = $this->makeAccount('2301', 'Due to IC', Account::TYPE_LIABILITY);
        $this->offsetFrom = $this->makeAccount('4101', 'IC Revenue', Account::TYPE_REVENUE);
        $this->offsetTo = $this->makeAccount('5101', 'IC Expense', Account::TYPE_EXPENSE);
    }

    protected function makeLedger(string $companyId, string $code): Ledger
    {
        return Ledger::create([
            'ledger_id'   => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'company_id'  => $companyId,
            'code'        => $code,
            'name'        => $code,
            'is_leading'  => true,
            'status'      => 1,
            'row_version' => 1,
        ]);
    }

    protected function makeAccount(string $code, string $name, int $type): Account
    {
        return Account::create([
            'account_id'     => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'account_code'   => $code,
            'name'           => $name,
            'account_type'   => $type,
            'is_postable'    => true,
            'normal_balance' => Account::BALANCE_DEBIT,
            'row_version'    => 1,
        ]);
    }

    #[Test]
    public function paired_ic_creates_two_draft_journals(): void
    {
        $svc = new IntercompanyJournalService();
        $svc->upsertAccountMap([
            'from_company_id'     => $this->fromCompanyId,
            'to_company_id'       => $this->toCompanyId,
            'due_from_account_id' => $this->dueFrom->account_id,
            'due_to_account_id'   => $this->dueTo->account_id,
        ]);

        $result = $svc->createPairedDrafts([
            'from_company_id'        => $this->fromCompanyId,
            'to_company_id'          => $this->toCompanyId,
            'from_ledger_id'         => $this->fromLedger->ledger_id,
            'to_ledger_id'           => $this->toLedger->ledger_id,
            'period_id'              => $this->periodId,
            'amount'                 => 5000,
            'from_offset_account_id' => $this->offsetFrom->account_id,
            'to_offset_account_id'   => $this->offsetTo->account_id,
            'description'            => 'فروش IC تست',
        ]);

        $this->assertInstanceOf(IcJournalPair::class, $result['pair']);
        $this->assertSame(IcJournalPair::STATUS_DRAFT_PAIR, $result['pair']->status);

        $fromJe = JournalEntry::find($result['from_journal_entry_id']);
        $toJe = JournalEntry::find($result['to_journal_entry_id']);
        $this->assertSame(JournalEntry::STATUS_DRAFT, $fromJe->status);
        $this->assertSame(JournalEntry::STATUS_DRAFT, $toJe->status);
        $this->assertSame($this->fromCompanyId, $fromJe->company_id);
        $this->assertSame($this->toCompanyId, $toJe->company_id);
    }

    #[Test]
    public function elimination_draft_on_elimination_entity(): void
    {
        $svc = new IntercompanyJournalService();
        $result = $svc->createEliminationDraft([
            'elimination_company_id' => $this->elimCompanyId,
            'ledger_id'              => $this->elimLedger->ledger_id,
            'period_id'              => $this->periodId,
            'due_from_account_id'    => $this->dueFrom->account_id,
            'due_to_account_id'      => $this->dueTo->account_id,
            'amount'                 => 5000,
        ]);

        $je = JournalEntry::find($result['journal_entry_id']);
        $this->assertNotNull($je);
        $this->assertSame(JournalEntry::STATUS_DRAFT, $je->status);
        $this->assertSame('IC_ELIMINATION', $je->source_document_type);
    }
}
