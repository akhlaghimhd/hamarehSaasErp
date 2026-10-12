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

/** ADR-FIN-002 FIN-CSN-F3-01 — sequence numbers unique & sequential for same company/FY. */
class DocumentSequenceConcurrencyTest extends TestCase
{
    #[Test]
    public function sequential_drafts_get_distinct_increasing_numbers(): void
    {
        $tenant = Tenant::factory()->create(['tenant_code' => 'FIN_SEQ']);
        TenantContext::getInstance()->setTenantId($tenant->tenant_id);

        $companyId = (string) Str::uuid();
        $periodId = (string) Str::uuid();

        Ledger::create([
            'ledger_id'   => (string) Str::uuid(),
            'tenant_id'   => $tenant->tenant_id,
            'company_id'  => $companyId,
            'code'        => 'LG',
            'name'        => 'L',
            'is_leading'  => true,
            'status'      => 1,
            'row_version' => 1,
        ]);

        $coa = new ChartOfAccountsService();
        $cash = $coa->create([
            'account_code'   => '1101',
            'name'           => 'صندوق',
            'account_type'   => Account::TYPE_ASSET,
            'normal_balance' => Account::BALANCE_DEBIT,
            'is_postable'    => true,
        ]);
        $rev = $coa->create([
            'account_code'   => '4101',
            'name'           => 'درآمد',
            'account_type'   => Account::TYPE_REVENUE,
            'normal_balance' => Account::BALANCE_CREDIT,
            'is_postable'    => true,
        ]);

        $svc = new JournalEntryService();
        $actor = (string) Str::uuid();
        $docDate = '2026-06-15';
        $lines = [
            ['account_id' => $cash->account_id, 'debit_amount' => 1, 'credit_amount' => 0],
            ['account_id' => $rev->account_id, 'debit_amount' => 0, 'credit_amount' => 1],
        ];

        $nums = [];
        for ($i = 0; $i < 5; $i++) {
            $d = $svc->createDraft([
                'company_id'    => $companyId,
                'period_id'     => $periodId,
                'document_date' => $docDate,
                'lines'         => $lines,
            ], $actor);
            $nums[] = (string) $d->draft_number;
        }

        $this->assertCount(5, array_unique($nums));
        $seqs = array_map(static function (string $n): int {
            if (preg_match('/-(\d{6})$/', $n, $m)) {
                return (int) $m[1];
            }

            return -1;
        }, $nums);

        $this->assertSame([1, 2, 3, 4, 5], $seqs);
    }
}
