<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalItem;
use Illuminate\Support\Facades\DB;

/**
 * FIN-P6-04 — non-blocking anomaly flags when line amount >> company history median.
 */
class AnomalyAmountService
{
    public function __construct(
        protected SmartActionAuditService $audit = new SmartActionAuditService()
    ) {
    }

    /**
     * @return list<array{code: string, severity: string, message: string, account_id?: string, amount?: float, threshold?: float}>
     */
    public function scanJournal(string $journalEntryId, ?string $actorId = null): array
    {
        $entry = JournalEntry::with('items')->where('journal_entry_id', $journalEntryId)->first();
        if (! $entry) {
            return [];
        }

        $companyId = (string) $entry->company_id;
        $alerts = [];

        foreach ($entry->items as $item) {
            $amt = max((float) $item->debit_amount, (float) $item->credit_amount);
            if ($amt <= 0) {
                continue;
            }

            $stats = $this->accountStats($companyId, (string) $item->account_id);
            if ($stats['count'] < 3) {
                continue;
            }

            // flag if > 5x median or > mean + 3*stddev (approx using avg)
            $median = $stats['median'];
            $avg = $stats['avg'];
            $threshold = max($median * 5, $avg * 4, 0.0001);

            if ($amt > $threshold) {
                $alerts[] = [
                    'code'       => 'AMOUNT_OUTLIER',
                    'severity'   => 'warn',
                    'message'    => 'مبلغ آرتیکل نسبت به تاریخچه این حساب در شرکت غیرعادی بزرگ است.',
                    'account_id' => (string) $item->account_id,
                    'amount'     => $amt,
                    'threshold'  => round($threshold, 4),
                ];
            }
        }

        $this->audit->log('ANOMALY', 'ANOMALY', 'GENERATED', [
            'journal_entry_id' => $journalEntryId,
            'count'            => count($alerts),
        ], $actorId, 'fin_acc_journal_entries', $journalEntryId);

        return $alerts;
    }

    /** @return array{count: int, avg: float, median: float} */
    protected function accountStats(string $companyId, string $accountId): array
    {
        $entryIds = JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('status', JournalEntry::STATUS_POSTED)
            ->limit(300)
            ->pluck('journal_entry_id');

        if ($entryIds->isEmpty()) {
            return ['count' => 0, 'avg' => 0.0, 'median' => 0.0];
        }

        $amounts = JournalItem::query()
            ->whereIn('journal_entry_id', $entryIds)
            ->where('account_id', $accountId)
            ->get()
            ->map(fn (JournalItem $i) => max((float) $i->debit_amount, (float) $i->credit_amount))
            ->filter(fn ($v) => $v > 0)
            ->sort()
            ->values();

        $n = $amounts->count();
        if ($n === 0) {
            return ['count' => 0, 'avg' => 0.0, 'median' => 0.0];
        }

        $avg = $amounts->avg();
        $mid = (int) floor(($n - 1) / 2);
        $median = $n % 2 === 1
            ? (float) $amounts[$mid]
            : (((float) $amounts[$mid] + (float) $amounts[$mid + 1]) / 2);

        return ['count' => $n, 'avg' => (float) $avg, 'median' => $median];
    }
}
