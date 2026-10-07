<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FIN-P0-15 — Trial balance for company + period (posted only).
 */
class TrialBalanceService
{
    /**
     * @return list<array{account_id: string, account_code: string, name: string, account_type: int, debit: string, credit: string, balance: string}>
     */
    public function run(string $companyId, string $periodId): array
    {
        $entryIds = JournalEntry::query()
            ->where('company_id', $companyId)
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
            /** @var Account|null $acc */
            $acc = $accounts->get($row->account_id);
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;
            $balance = $debit - $credit;

            return [
                'account_id'   => $row->account_id,
                'account_code' => $acc?->account_code ?? '',
                'name'         => $acc?->name ?? '',
                'account_type' => $acc?->account_type ?? 0,
                'debit'        => number_format($debit, 4, '.', ''),
                'credit'       => number_format($credit, 4, '.', ''),
                'balance'      => number_format($balance, 4, '.', ''),
            ];
        })->sortBy('account_code')->values()->all();
    }
}
