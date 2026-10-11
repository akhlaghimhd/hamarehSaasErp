<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\ChartOfAccountsService;
use App\Modules\FinancialAccounting\Application\Services\JournalEntryService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADR-FIN-002 / FIN-CSN-F0-08 — numbering format and dual series basics.
 * Full sibling/parent Scope matrix is covered when Holding Scope fixtures are available;
 * soft-path (no COMPANY scopes) is the default in this test harness.
 */
class JournalCompanyScopeNumberingTest extends TestCase
{
    protected Tenant $tenant;

    protected string $companyId;

    protected string $periodId;

    protected Ledger $ledger;

    protected Account $cash;

    protected Account $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_CSN']);
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

        $coa = new ChartOfAccountsService();
        $this->cash = $coa->create([
            'account_code'   => '1101',
            'name'           => 'صندوق',
            'account_type'   => Account::TYPE_ASSET,
            'normal_balance' => Account::BALANCE_DEBIT,
            'is_postable'    => true,
        ]);
        $this->revenue = $coa->create([
            'account_code'   => '4101',
            'name'           => 'درآمد',
            'account_type'   => Account::TYPE_REVENUE,
            'normal_balance' => Account::BALANCE_CREDIT,
            'is_postable'    => true,
        ]);
    }

    #[Test]
    public function draft_uses_dr_fy_seq_and_post_assigns_je_keeping_draft_number(): void
    {
        $svc = new JournalEntryService();
        $actor = (string) Str::uuid();
        $year = date('Y');

        $draft = $svc->createDraft([
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => $year.'-06-15',
            'description'   => 'CSN draft',
            'lines'         => [
                ['account_id' => $this->cash->account_id, 'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => $this->revenue->account_id, 'debit_amount' => 0, 'credit_amount' => 100],
            ],
        ], $actor);

        $this->assertNotNull($draft->draft_number);
        $this->assertMatchesRegularExpression('/^DR-'.$year.'-\d{6}$/', (string) $draft->draft_number);
        $this->assertSame($draft->draft_number, $draft->entry_number);

        $posted = $svc->post((string) $draft->journal_entry_id, $actor);

        $this->assertMatchesRegularExpression('/^JE-'.$year.'-\d{6}$/', (string) $posted->entry_number);
        $this->assertMatchesRegularExpression('/^DR-'.$year.'-\d{6}$/', (string) $posted->draft_number);
        $this->assertNotSame($posted->draft_number, $posted->entry_number);
    }

    #[Test]
    public function two_companies_have_independent_sequence_series(): void
    {
        $companyB = (string) Str::uuid();
        Ledger::create([
            'ledger_id'   => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'company_id'  => $companyB,
            'code'        => 'LB',
            'name'        => 'B',
            'is_leading'  => true,
            'status'      => 1,
            'row_version' => 1,
        ]);

        $svc = new JournalEntryService();
        $actor = (string) Str::uuid();
        $year = date('Y');
        $lines = function () {
            return [
                ['account_id' => $this->cash->account_id, 'debit_amount' => 10, 'credit_amount' => 0],
                ['account_id' => $this->revenue->account_id, 'debit_amount' => 0, 'credit_amount' => 10],
            ];
        };

        $a = $svc->createDraft([
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => $year.'-01-01',
            'lines'         => $lines(),
        ], $actor);

        $b = $svc->createDraft([
            'company_id'    => $companyB,
            'period_id'     => $this->periodId,
            'document_date' => $year.'-01-01',
            'lines'         => $lines(),
        ], $actor);

        $this->assertSame('DR-'.$year.'-000001', $a->draft_number);
        $this->assertSame('DR-'.$year.'-000001', $b->draft_number);
        $this->assertNotSame($a->company_id, $b->company_id);
    }
}
