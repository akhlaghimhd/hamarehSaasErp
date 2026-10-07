<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Application\Services\ChartOfAccountsService;
use App\Modules\FinancialAccounting\Application\Services\FiscalPeriodControlService;
use App\Modules\FinancialAccounting\Application\Services\JournalEntryService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FIN-P0-10..14 feature coverage: CoA, period lock, draft/post/reverse.
 */
class JournalAndCoaFeatureTest extends TestCase
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

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_P0']);
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
    public function coa_rejects_duplicate_code_and_delete_with_movement(): void
    {
        $coa = new ChartOfAccountsService();

        $this->expectException(DomainException::class);
        $coa->create([
            'account_code' => '1101',
            'name'         => 'تکراری',
            'account_type' => Account::TYPE_ASSET,
        ]);
    }

    #[Test]
    public function draft_may_be_unbalanced_but_post_requires_balance(): void
    {
        $svc = new JournalEntryService();

        $draft = $svc->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'lines'         => [
                [
                    'account_id'   => $this->cash->account_id,
                    'debit_amount' => 100,
                ],
            ],
        ]);

        $this->assertTrue($draft->isDraft());

        $this->expectException(DomainException::class);
        $svc->post($draft->journal_entry_id);
    }

    #[Test]
    public function post_and_reverse_happy_path(): void
    {
        $svc = new JournalEntryService();

        $draft = $svc->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'description'   => 'فروش نقدی',
            'lines'         => [
                [
                    'account_id'   => $this->cash->account_id,
                    'debit_amount' => 1500.5,
                ],
                [
                    'account_id'    => $this->revenue->account_id,
                    'credit_amount' => 1500.5,
                ],
            ],
        ]);

        $posted = $svc->post($draft->journal_entry_id);
        $this->assertSame(JournalEntry::STATUS_POSTED, $posted->status);
        $this->assertNotNull($posted->entry_number);
        $this->assertStringStartsWith('JE-', $posted->entry_number);

        $reverse = $svc->reverse($posted->journal_entry_id);
        $this->assertSame(JournalEntry::STATUS_POSTED, $reverse->status);
        $this->assertSame($posted->journal_entry_id, $reverse->reverses_entry_id);

        $original = $svc->find($posted->journal_entry_id);
        $this->assertSame(JournalEntry::STATUS_REVERSED, $original->status);
        $this->assertSame($reverse->journal_entry_id, $original->reversed_by_entry_id);
    }

    #[Test]
    public function hard_closed_period_blocks_post(): void
    {
        $periodSvc = new FiscalPeriodControlService();
        $periodSvc->hardClose($this->companyId, $this->periodId);

        $svc = new JournalEntryService();
        $draft = $svc->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'lines'         => [
                ['account_id' => $this->cash->account_id, 'debit_amount' => 10],
                ['account_id' => $this->revenue->account_id, 'credit_amount' => 10],
            ],
        ]);

        $this->expectException(DomainException::class);
        $svc->post($draft->journal_entry_id);
    }

    #[Test]
    public function cannot_delete_account_with_posted_lines(): void
    {
        $svc = new JournalEntryService();
        $draft = $svc->createDraft([
            'ledger_id'     => $this->ledger->ledger_id,
            'company_id'    => $this->companyId,
            'period_id'     => $this->periodId,
            'document_date' => now()->toDateString(),
            'lines'         => [
                ['account_id' => $this->cash->account_id, 'debit_amount' => 50],
                ['account_id' => $this->revenue->account_id, 'credit_amount' => 50],
            ],
        ]);
        $svc->post($draft->journal_entry_id);

        $coa = new ChartOfAccountsService();
        $this->expectException(DomainException::class);
        $coa->softDelete($this->cash->account_id);
    }
}
