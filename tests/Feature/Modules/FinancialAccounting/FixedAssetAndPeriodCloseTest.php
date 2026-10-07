<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Application\Services\FixedAssetService;
use App\Modules\FinancialAccounting\Application\Services\JournalEntryService;
use App\Modules\FinancialAccounting\Application\Services\PeriodCloseChecklistService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\FinancialAccounting\Infrastructure\Models\PeriodControl;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FixedAssetAndPeriodCloseTest extends TestCase
{
    protected Tenant $tenant;

    protected string $companyId;

    protected string $periodId;

    protected Ledger $ledger;

    protected Account $assetAcc;

    protected Account $accumAcc;

    protected Account $expAcc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_P4']);
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

        $this->assetAcc = $this->makeAccount('1501', 'دارایی ثابت', Account::TYPE_ASSET);
        $this->accumAcc = $this->makeAccount('1502', 'استهلاک انباشته', Account::TYPE_ASSET);
        $this->expAcc = $this->makeAccount('5103', 'هزینه استهلاک', Account::TYPE_EXPENSE);

        PeriodControl::create([
            'period_control_id' => (string) Str::uuid(),
            'tenant_id'         => $this->tenant->tenant_id,
            'company_id'        => $this->companyId,
            'period_id'         => $this->periodId,
            'control_status'    => PeriodControl::STATUS_OPEN,
            'row_version'       => 1,
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
            'normal_balance' => $type === Account::TYPE_EXPENSE ? Account::BALANCE_DEBIT : Account::BALANCE_DEBIT,
            'row_version'    => 1,
        ]);
    }

    #[Test]
    public function depreciation_creates_draft_journal_not_posted(): void
    {
        $svc = new FixedAssetService();
        $svc->create([
            'company_id'              => $this->companyId,
            'asset_code'              => 'FA-001',
            'name'                    => 'ماشین',
            'asset_account_id'        => $this->assetAcc->account_id,
            'accum_depr_account_id'   => $this->accumAcc->account_id,
            'depr_expense_account_id' => $this->expAcc->account_id,
            'acquisition_date'        => '2026-01-01',
            'acquisition_cost'        => 12000,
            'salvage_value'           => 0,
            'useful_life_months'      => 12,
        ]);

        $result = $svc->runDepreciation(
            $this->companyId,
            $this->periodId,
            $this->ledger->ledger_id
        );

        $this->assertNotNull($result['journal_entry_id']);
        $je = JournalEntry::where('journal_entry_id', $result['journal_entry_id'])->first();
        $this->assertNotNull($je);
        $this->assertSame(JournalEntry::STATUS_DRAFT, $je->status);
        $this->assertEquals(1000.0, (float) $result['run']->total_amount);
    }

    #[Test]
    public function post_requires_cost_center_when_account_flagged(): void
    {
        $this->expAcc->requires_cost_center = true;
        $this->expAcc->save();

        $svc = new JournalEntryService();
        $draft = $svc->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'lines'         => [
                ['account_id' => $this->expAcc->account_id, 'debit_amount' => 50, 'credit_amount' => 0],
                ['account_id' => $this->accumAcc->account_id, 'debit_amount' => 0, 'credit_amount' => 50],
            ],
        ]);

        $this->expectException(DomainException::class);
        $svc->post($draft->journal_entry_id);
    }

    #[Test]
    public function guided_close_blocked_by_open_drafts(): void
    {
        $svc = new JournalEntryService();
        $svc->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'lines'         => [
                ['account_id' => $this->expAcc->account_id, 'debit_amount' => 10, 'credit_amount' => 0],
                ['account_id' => $this->accumAcc->account_id, 'debit_amount' => 0, 'credit_amount' => 10],
            ],
        ]);

        $close = new PeriodCloseChecklistService();
        $eval = $close->evaluate($this->companyId, $this->periodId);
        $this->assertTrue($eval['checklist']->has_blocking);

        $this->expectException(DomainException::class);
        $close->softCloseGuided($this->companyId, $this->periodId);
    }
}
