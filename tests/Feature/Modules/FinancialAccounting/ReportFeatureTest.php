<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\BalanceSheetService;
use App\Modules\FinancialAccounting\Application\Services\ChartOfAccountsService;
use App\Modules\FinancialAccounting\Application\Services\JournalEntryService;
use App\Modules\FinancialAccounting\Application\Services\ProfitAndLossService;
use App\Modules\FinancialAccounting\Application\Services\TrialBalanceService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** FIN-P0-19 — Trial balance / P&L / BS after post. */
class ReportFeatureTest extends TestCase
{
    protected Tenant $tenant;

    protected string $companyId;

    protected string $periodId;

    protected Ledger $ledger;

    protected Account $cash;

    protected Account $revenue;

    protected Account $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_RPT']);
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
            'name'           => 'فروش',
            'account_type'   => Account::TYPE_REVENUE,
            'normal_balance' => Account::BALANCE_CREDIT,
            'is_postable'    => true,
        ]);
        $this->expense = $coa->create([
            'account_code'   => '5101',
            'name'           => 'هزینه',
            'account_type'   => Account::TYPE_EXPENSE,
            'normal_balance' => Account::BALANCE_DEBIT,
            'is_postable'    => true,
        ]);
    }

    #[Test]
    public function trial_balance_and_pl_after_sales_and_expense(): void
    {
        $svc = new JournalEntryService();

        // Sale: Dr cash 1000 / Cr revenue 1000
        $sale = $svc->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'lines'         => [
                ['account_id' => $this->cash->account_id, 'debit_amount' => 1000],
                ['account_id' => $this->revenue->account_id, 'credit_amount' => 1000],
            ],
        ]);
        $svc->post($sale->journal_entry_id);

        // Expense: Dr expense 300 / Cr cash 300
        $exp = $svc->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'lines'         => [
                ['account_id' => $this->expense->account_id, 'debit_amount' => 300],
                ['account_id' => $this->cash->account_id, 'credit_amount' => 300],
            ],
        ]);
        $svc->post($exp->journal_entry_id);

        $tb = (new TrialBalanceService())->run($this->companyId, $this->periodId);
        $this->assertNotEmpty($tb);

        $cashRow = collect($tb)->firstWhere('account_id', $this->cash->account_id);
        $this->assertNotNull($cashRow);
        $this->assertEquals(700.0, (float) $cashRow['balance']);

        $pl = (new ProfitAndLossService())->run($this->companyId, $this->periodId);
        $this->assertEquals(1000.0, (float) $pl['total_revenue']);
        $this->assertEquals(300.0, (float) $pl['total_expense']);
        $this->assertEquals(700.0, (float) $pl['net_income']);

        $bs = (new BalanceSheetService())->run($this->companyId, $this->periodId);
        $this->assertEquals(700.0, (float) $bs['total_assets']);
        $this->assertEqualsWithDelta(
            (float) $bs['total_assets'],
            (float) $bs['total_liabilities_equity'],
            0.0001
        );
    }
}
