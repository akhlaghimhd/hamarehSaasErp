<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalItem;
use App\Modules\Organization\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * FIN-P5-04 — Lean consolidated TB: sum POSTED of OPERATING companies under a CONSOLIDATION root.
 * No currency translation / ownership % in P5.
 */
class ConsolidatedTrialBalanceService
{
    /**
     * @return list<array{account_id: string, account_code: string, name: string, account_type: int, debit: string, credit: string, balance: string}>
     */
    public function run(string $consolidationCompanyId, string $periodId): array
    {
        $root = Company::query()->where('company_id', $consolidationCompanyId)->first();
        if (! $root) {
            throw new DomainException('شرکت تلفیق یافت نشد.', 'fin.consol.company_missing');
        }

        $companyIds = $this->operatingDescendants($consolidationCompanyId);
        if ($companyIds === []) {
            // include self if operating
            if ($root->entity_kind === Company::ENTITY_KIND_OPERATING) {
                $companyIds = [$consolidationCompanyId];
            }
        }

        if ($companyIds === []) {
            return [];
        }

        $entryIds = JournalEntry::query()
            ->whereIn('company_id', $companyIds)
            ->where('period_id', $periodId)
            ->where('status', JournalEntry::STATUS_POSTED)
            ->pluck('journal_entry_id');

        if ($entryIds->isEmpty()) {
            return [];
        }

        $rows = JournalItem::query()
            ->select([
                'account_id',
                DB::raw('SUM(debit_amount) as total_debit'),
                DB::raw('SUM(credit_amount) as total_credit'),
            ])
            ->whereIn('journal_entry_id', $entryIds)
            ->groupBy('account_id')
            ->get();

        $accounts = Account::query()
            ->whereIn('account_id', $rows->pluck('account_id'))
            ->get()
            ->keyBy('account_id');

        return $rows->map(function ($row) use ($accounts) {
            $acc = $accounts->get($row->account_id);
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            return [
                'account_id'   => $row->account_id,
                'account_code' => $acc?->account_code ?? '',
                'name'         => $acc?->name ?? '',
                'account_type' => $acc?->account_type ?? 0,
                'debit'        => number_format($debit, 4, '.', ''),
                'credit'       => number_format($credit, 4, '.', ''),
                'balance'      => number_format($debit - $credit, 4, '.', ''),
            ];
        })->sortBy('account_code')->values()->all();
    }

    /**
     * @return list<string>
     */
    protected function operatingDescendants(string $rootId): array
    {
        $ids = [];
        $queue = [$rootId];
        $seen = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            $children = Company::query()
                ->where('parent_company_id', $current)
                ->get(['company_id', 'entity_kind']);

            foreach ($children as $child) {
                if ($child->entity_kind === Company::ENTITY_KIND_OPERATING) {
                    $ids[] = (string) $child->company_id;
                }
                // walk further under CONSOLIDATION nodes too
                if (in_array($child->entity_kind, [
                    Company::ENTITY_KIND_OPERATING,
                    Company::ENTITY_KIND_CONSOLIDATION,
                ], true)) {
                    $queue[] = (string) $child->company_id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
