<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalItem;
use Illuminate\Support\Facades\DB;

/**
 * FIN-P6-01 K2 — suggest account from posting history (frequency in company).
 * Non-binding; caller may override.
 */
class AccountSuggestionService
{
    public function __construct(
        protected SmartActionAuditService $audit = new SmartActionAuditService()
    ) {
    }

    /**
     * @return array{
     *   suggestions: list<array{account_id: string, account_code: string, name: string, score: int, reason: string}>,
     *   override_allowed: true
     * }
     */
    public function suggest(
        string $companyId,
        ?string $descriptionHint = null,
        ?int $side = null, // 1 debit preference, 2 credit
        ?string $actorId = null,
    ): array {
        $this->requireTenant();

        $entryIds = JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('status', JournalEntry::STATUS_POSTED)
            ->orderByDesc('posting_date')
            ->limit(500)
            ->pluck('journal_entry_id');

        if ($entryIds->isEmpty()) {
            $fallback = $this->fallbackPostable(5);
            $this->audit->log('K2', 'ACCOUNT_SUGGEST', 'GENERATED', [
                'company_id' => $companyId,
                'mode'       => 'fallback_empty_history',
                'count'      => count($fallback),
            ], $actorId);

            return ['suggestions' => $fallback, 'override_allowed' => true];
        }

        $q = JournalItem::query()
            ->select([
                'account_id',
                DB::raw('COUNT(*) as use_count'),
                DB::raw('SUM(debit_amount) as sum_debit'),
                DB::raw('SUM(credit_amount) as sum_credit'),
            ])
            ->whereIn('journal_entry_id', $entryIds)
            ->groupBy('account_id')
            ->orderByDesc('use_count')
            ->limit(20);

        if ($descriptionHint) {
            $like = '%'.mb_substr($descriptionHint, 0, 40).'%';
            $q->where(function ($w) use ($like) {
                $w->where('description', 'ilike', $like);
            });
            // if no match, re-run without filter below
        }

        $rows = $q->get();
        if ($descriptionHint && $rows->isEmpty()) {
            $rows = JournalItem::query()
                ->select([
                    'account_id',
                    DB::raw('COUNT(*) as use_count'),
                    DB::raw('SUM(debit_amount) as sum_debit'),
                    DB::raw('SUM(credit_amount) as sum_credit'),
                ])
                ->whereIn('journal_entry_id', $entryIds)
                ->groupBy('account_id')
                ->orderByDesc('use_count')
                ->limit(10)
                ->get();
        }

        $accounts = Account::query()
            ->whereIn('account_id', $rows->pluck('account_id'))
            ->where('is_postable', true)
            ->get()
            ->keyBy('account_id');

        $suggestions = [];
        foreach ($rows as $row) {
            $acc = $accounts->get($row->account_id);
            if (! $acc) {
                continue;
            }
            if ($side === 1 && (float) $row->sum_debit <= 0 && (float) $row->sum_credit > 0) {
                // prefer debit-side history when asking for debit
                continue;
            }
            if ($side === 2 && (float) $row->sum_credit <= 0 && (float) $row->sum_debit > 0) {
                continue;
            }

            $score = (int) $row->use_count;
            $reason = "از تاریخچه ثبت‌های شرکت ({$score} بار)";
            if ($descriptionHint) {
                $reason .= ' با شباهت توضیح';
            }

            $suggestions[] = [
                'account_id'   => (string) $acc->account_id,
                'account_code' => (string) $acc->account_code,
                'name'         => (string) $acc->name,
                'score'        => $score,
                'reason'       => $reason,
            ];
        }

        if ($suggestions === []) {
            $suggestions = $this->fallbackPostable(5);
        }

        $this->audit->log('K2', 'ACCOUNT_SUGGEST', 'GENERATED', [
            'company_id' => $companyId,
            'hint'       => $descriptionHint,
            'count'      => count($suggestions),
        ], $actorId);

        return [
            'suggestions'      => array_slice($suggestions, 0, 8),
            'override_allowed' => true,
        ];
    }

    public function recordDecision(
        string $decision,
        ?string $suggestedAccountId,
        ?string $chosenAccountId,
        ?string $actorId = null,
        ?array $context = null,
    ): void {
        $this->audit->log(
            'K2',
            'ACCOUNT_SUGGEST',
            $decision,
            array_merge($context ?? [], [
                'suggested_account_id' => $suggestedAccountId,
                'chosen_account_id'    => $chosenAccountId,
            ]),
            $actorId,
            'fin_acc_accounts',
            $chosenAccountId ?? $suggestedAccountId
        );
    }

    /** @return list<array{account_id: string, account_code: string, name: string, score: int, reason: string}> */
    protected function fallbackPostable(int $limit): array
    {
        return Account::query()
            ->where('is_postable', true)
            ->orderBy('account_code')
            ->limit($limit)
            ->get()
            ->map(fn (Account $a) => [
                'account_id'   => (string) $a->account_id,
                'account_code' => (string) $a->account_code,
                'name'         => (string) $a->name,
                'score'        => 0,
                'reason'       => 'پیشنهاد پیش‌فرض (بدون تاریخچه کافی)',
            ])
            ->all();
    }

    protected function requireTenant(): void
    {
        if (! TenantContext::getInstance()->getTenantId()) {
            throw new DomainException('بافت مستأجر تنظیم نشده است.', 'fin.tenant_missing');
        }
    }
}
