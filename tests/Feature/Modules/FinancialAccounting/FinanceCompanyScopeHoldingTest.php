<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\ScopeContext;
use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Application\Services\ChartOfAccountsService;
use App\Modules\FinancialAccounting\Application\Services\FinanceCompanyScope;
use App\Modules\FinancialAccounting\Application\Services\JournalEntryService;
use App\Modules\FinancialAccounting\Application\Services\TrialBalanceService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\Organization\Models\Company;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADR-FIN-003 FIN-HOLD-W1 — read vs operate company scope.
 * Product B (2026-10-16): inactive company blocks operate; read remains.
 */
class FinanceCompanyScopeHoldingTest extends TestCase
{
    protected Tenant $tenant;

    protected string $companyA;

    protected string $companyB;

    protected string $periodId;

    protected Ledger $ledgerA;

    protected Ledger $ledgerB;

    protected Account $cash;

    protected Account $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_HOLD']);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);

        $this->companyA = (string) Str::uuid();
        $this->companyB = (string) Str::uuid();
        $this->periodId = (string) Str::uuid();

        DB::table('erp_companies')->insert([
            [
                'company_id'  => $this->companyA,
                'tenant_id'   => $this->tenant->tenant_id,
                'code'        => 'CO-A',
                'name'        => 'Company A',
                'legal_name'  => 'Company A',
                'is_active'   => true,
                'is_primary'  => true,
                'entity_kind' => Company::ENTITY_KIND_OPERATING,
                'status'      => 1,
                'row_version' => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'company_id'  => $this->companyB,
                'tenant_id'   => $this->tenant->tenant_id,
                'code'        => 'CO-B',
                'name'        => 'Company B',
                'legal_name'  => 'Company B',
                'is_active'   => true,
                'is_primary'  => false,
                'entity_kind' => Company::ENTITY_KIND_OPERATING,
                'status'      => 1,
                'row_version' => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        DB::table('erp_companies')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('company_id', '!=', $this->companyA)
            ->update(['is_primary' => false]);
        DB::table('erp_companies')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('company_id', $this->companyA)
            ->update(['is_primary' => true]);

        $this->ledgerA = Ledger::create([
            'ledger_id'   => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'company_id'  => $this->companyA,
            'code'        => 'LGA',
            'name'        => 'A',
            'is_leading'  => true,
            'status'      => 1,
            'row_version' => 1,
        ]);
        $this->ledgerB = Ledger::create([
            'ledger_id'   => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'company_id'  => $this->companyB,
            'code'        => 'LGB',
            'name'        => 'B',
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

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        Context::flush();
        parent::tearDown();
    }

    protected function actAsCompanyScoped(string $companyId): void
    {
        $scopeId = (string) Str::uuid();
        Context::add('security_context', [
            'is_owner'  => false,
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => (string) Str::uuid(),
        ]);
        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => $scopeId,
                'scope_type'   => 'COMPANY',
                'reference_id' => $companyId,
                'scope_name'   => 'Co',
            ],
        ]);
    }

    /** Tenant owner: group-wide READ only under FIN-HOLD-W1 (not operate-all). */
    protected function actAsOwnerGroupWide(): void
    {
        Context::add('security_context', [
            'is_owner'  => true,
            'tenant_id' => $this->tenant->tenant_id,
        ]);
        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([]);
    }

    /** SME: authenticated, no COMPANY scopes → primary only. */
    protected function actAsSmeNoCompanyScope(): void
    {
        Context::add('security_context', [
            'is_owner'  => false,
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => (string) Str::uuid(),
        ]);
        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([]);
    }

    /** @return list<array<string, mixed>> */
    protected function balancedLines(): array
    {
        return [
            ['account_id' => $this->cash->account_id, 'debit_amount' => 50, 'credit_amount' => 0],
            ['account_id' => $this->revenue->account_id, 'debit_amount' => 0, 'credit_amount' => 50],
        ];
    }

    #[Test]
    public function company_scoped_actor_can_create_on_own_company_not_sibling(): void
    {
        $this->actAsCompanyScoped($this->companyA);
        $svc = new JournalEntryService();
        $actor = (string) Str::uuid();

        $draft = $svc->createDraft([
            'ledger_id'     => $this->ledgerA->ledger_id,
            'company_id'    => $this->companyA,
            'period_id'     => $this->periodId,
            'document_date' => '2026-06-15',
            'lines'         => $this->balancedLines(),
        ], $actor);

        $this->assertNotNull($draft->draft_number);
        $this->assertSame($this->companyA, $draft->company_id);

        $this->expectException(DomainException::class);
        $svc->createDraft([
            'ledger_id'     => $this->ledgerB->ledger_id,
            'company_id'    => $this->companyB,
            'period_id'     => $this->periodId,
            'document_date' => '2026-06-15',
            'lines'         => $this->balancedLines(),
        ], $actor);
    }

    #[Test]
    public function company_scoped_list_excludes_sibling_journals(): void
    {
        $svc = new JournalEntryService();
        $actor = (string) Str::uuid();

        $this->actAsCompanyScoped($this->companyA);
        $a = $svc->createDraft([
            'ledger_id'     => $this->ledgerA->ledger_id,
            'company_id'    => $this->companyA,
            'period_id'     => $this->periodId,
            'document_date' => '2026-06-15',
            'lines'         => $this->balancedLines(),
        ], $actor);

        $this->actAsCompanyScoped($this->companyB);
        $b = $svc->createDraft([
            'ledger_id'     => $this->ledgerB->ledger_id,
            'company_id'    => $this->companyB,
            'period_id'     => $this->periodId,
            'document_date' => '2026-06-15',
            'lines'         => $this->balancedLines(),
        ], $actor);

        $this->actAsCompanyScoped($this->companyA);
        $svcScoped = new JournalEntryService();
        $list = $svcScoped->list([]);
        $ids = $list->pluck('journal_entry_id')->map(fn ($id) => (string) $id)->all();

        $this->assertContains((string) $a->journal_entry_id, $ids);
        $this->assertNotContains((string) $b->journal_entry_id, $ids);
    }

    #[Test]
    public function company_scoped_cannot_run_trial_balance_on_sibling(): void
    {
        $this->actAsCompanyScoped($this->companyA);
        $tb = new TrialBalanceService();

        $this->expectException(DomainException::class);
        $tb->run($this->companyB, $this->periodId);
    }

    #[Test]
    public function owner_group_wide_can_read_both_companies_but_cannot_operate(): void
    {
        $this->actAsCompanyScoped($this->companyA);
        $svc = new JournalEntryService();
        $actor = (string) Str::uuid();
        $a = $svc->createDraft([
            'ledger_id'     => $this->ledgerA->ledger_id,
            'company_id'    => $this->companyA,
            'period_id'     => $this->periodId,
            'document_date' => '2026-06-15',
            'lines'         => $this->balancedLines(),
        ], $actor);

        $this->actAsCompanyScoped($this->companyB);
        $b = $svc->createDraft([
            'ledger_id'     => $this->ledgerB->ledger_id,
            'company_id'    => $this->companyB,
            'period_id'     => $this->periodId,
            'document_date' => '2026-06-15',
            'lines'         => $this->balancedLines(),
        ], $actor);

        $this->actAsOwnerGroupWide();
        $scope = new FinanceCompanyScope();
        $this->assertTrue($scope->isGroupWideRead());
        $this->assertFalse($scope->isGroupWideOperate());

        $list = $svc->list([]);
        $ids = $list->pluck('journal_entry_id')->map(fn ($id) => (string) $id)->all();
        $this->assertContains((string) $a->journal_entry_id, $ids);
        $this->assertContains((string) $b->journal_entry_id, $ids);

        $found = $svc->find((string) $b->journal_entry_id);
        $this->assertSame($this->companyB, $found->company_id);

        $this->expectException(DomainException::class);
        $svc->createDraft([
            'ledger_id'     => $this->ledgerB->ledger_id,
            'company_id'    => $this->companyB,
            'period_id'     => $this->periodId,
            'document_date' => '2026-06-15',
            'lines'         => $this->balancedLines(),
        ], $actor);
    }

    #[Test]
    public function sme_without_company_scope_only_primary_company(): void
    {
        $this->actAsSmeNoCompanyScope();
        $scope = new FinanceCompanyScope();

        $this->assertFalse($scope->isGroupWideRead());
        $this->assertFalse($scope->isGroupWideOperate());

        $allowed = $scope->allowedCompanyIds();
        $this->assertCount(1, $allowed);
        $this->assertSame($this->companyA, $allowed[0]);

        $primaryInDb = DB::table('erp_companies')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('is_primary', true)
            ->whereNull('deleted_at')
            ->value('company_id');
        $this->assertSame($this->companyA, (string) $primaryInDb);

        $scope->assertCanAccessCompany($this->companyA);

        $this->expectException(DomainException::class);
        $scope->assertCanAccessCompany($this->companyB);
    }

    #[Test]
    public function inactive_company_blocks_operate_but_allows_read(): void
    {
        DB::table('erp_companies')
            ->where('company_id', $this->companyA)
            ->update(['is_active' => false, 'status' => 0, 'updated_at' => now()]);

        $this->actAsCompanyScoped($this->companyA);
        $scope = new FinanceCompanyScope();

        $this->assertFalse($scope->isCompanyActive($this->companyA));
        $this->assertTrue($scope->isCompanyActive($this->companyB));

        // Read still allowed on scoped inactive company
        $scope->assertCanReadCompany($this->companyA);

        // Operate denied with inactive-specific code
        try {
            $scope->assertCanOperateCompany($this->companyA);
            $this->fail('Expected DomainException for inactive company operate');
        } catch (DomainException $e) {
            $this->assertSame('fin.company_scope.company_inactive', $e->getErrorCode());
        }

        $this->assertSame([], $scope->allowedCompanyIdsForOperate());

        $svc = new JournalEntryService();
        $actor = (string) Str::uuid();
        $this->expectException(DomainException::class);
        $svc->createDraft([
            'ledger_id'     => $this->ledgerA->ledger_id,
            'company_id'    => $this->companyA,
            'period_id'     => $this->periodId,
            'document_date' => '2026-06-15',
            'lines'         => $this->balancedLines(),
        ], $actor);
    }
}
