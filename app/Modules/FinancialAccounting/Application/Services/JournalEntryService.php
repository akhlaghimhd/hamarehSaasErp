<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\DocumentSequence;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalItem;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FIN-P0-12..14 + ADR-FIN-002 (FIN-CSN-F0-05/06/07) + FIN-HOLD-W1
 * Dual series: DR-{JalaliFY}-{SEQ6} on draft, JE-{JalaliFY}-{SEQ6} on post (per company).
 * FY prefers fin_fiscal_periods for period_id (D6); else document date. DB full year; UI 2-digit.
 * Access: FinanceCompanyScope on every mutation/read/list — operate vs read split (L03).
 */
class JournalEntryService
{
    public function __construct(
        protected FiscalPeriodControlService $periodControl = new FiscalPeriodControlService(),
        protected FinanceEventPublisher $events = new FinanceEventPublisher(),
        protected FinanceCompanyScope $companyScope = new FinanceCompanyScope()
    ) {
    }

    /**
     * @param  array{
     *   ledger_id?: string,
     *   company_id: string,
     *   period_id: string,
     *   document_date: string,
     *   description?: string|null,
     *   source_document_type?: string|null,
     *   source_document_id?: string|null,
     *   lines: list<array<string, mixed>>
     * }  $payload
     */
    public function createDraft(array $payload, ?string $actorId = null): JournalEntry
    {
        $tenantId = $this->requireTenantId();
        $companyId = (string) ($payload['company_id'] ?? '');
        // FIN-HOLD-W1 / L03: create is operate, not read
        $this->companyScope->assertCanOperateCompany($companyId);

        if (empty($payload['ledger_id'])) {
            $payload['ledger_id'] = $this->resolveLeadingLedgerId($companyId);
        }
        $this->assertLedger((string) $payload['ledger_id']);

        return DB::transaction(function () use ($tenantId, $payload, $actorId, $companyId) {
            $fy = $this->resolveFiscalYearKey(
                (string) $payload['period_id'],
                (string) ($payload['document_date'] ?? '')
            );
            $draftNumber = $this->nextSequenceNumber(
                $companyId,
                $fy,
                'JOURNAL_DR',
                'DR'
            );

            $entry = JournalEntry::create([
                'journal_entry_id'     => (string) Str::uuid(),
                'tenant_id'            => $tenantId,
                'ledger_id'            => $payload['ledger_id'],
                'company_id'           => $companyId,
                'period_id'            => $payload['period_id'],
                'document_date'        => $payload['document_date'],
                'status'               => JournalEntry::STATUS_DRAFT,
                'draft_number'         => $draftNumber,
                'entry_number'         => $draftNumber,
                'description'          => $payload['description'] ?? null,
                'source_document_type' => $payload['source_document_type'] ?? null,
                'source_document_id'   => $payload['source_document_id'] ?? null,
                'created_by'           => $actorId,
                'row_version'          => 1,
            ]);

            $this->replaceLines($entry, $payload['lines'] ?? [], $tenantId);

            return $entry->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateDraft(string $journalEntryId, array $payload, ?string $actorId = null): JournalEntry
    {
        $entry = $this->find($journalEntryId);

        if (! $entry->isDraft()) {
            throw new DomainException(
                'فقط پیش‌نویس قابل ویرایش است.',
                'fin.journal.not_draft'
            );
        }

        $this->assertCanMutateDraft($entry, $actorId);

        return DB::transaction(function () use ($entry, $payload) {
            if (isset($payload['document_date'])) {
                $entry->document_date = $payload['document_date'];
            }
            if (array_key_exists('description', $payload)) {
                $entry->description = $payload['description'];
            }
            if (isset($payload['period_id'])) {
                $entry->period_id = $payload['period_id'];
            }
            $entry->row_version = ((int) ($entry->row_version ?? 1)) + 1;
            $entry->save();

            if (isset($payload['lines']) && is_array($payload['lines'])) {
                JournalItem::where('journal_entry_id', $entry->journal_entry_id)->delete();
                $this->replaceLines($entry, $payload['lines'], (string) $entry->tenant_id);
            }

            return $entry->fresh(['items']);
        });
    }

    public function deleteDraft(string $journalEntryId, ?string $actorId = null): void
    {
        $entry = $this->find($journalEntryId);

        if (! $entry->isDraft()) {
            throw new DomainException(
                'فقط پیش‌نویس قابل حذف است. سند قطعی را برگشت بزنید.',
                'fin.journal.delete_only_draft'
            );
        }

        $this->assertCanMutateDraft($entry, $actorId);

        DB::transaction(function () use ($entry, $actorId) {
            $entry->deleted_by = $actorId;
            $entry->row_version = ((int) ($entry->row_version ?? 1)) + 1;
            $entry->save();
            $entry->delete();
        });
    }

    public function restoreDraft(string $journalEntryId, ?string $actorId = null): JournalEntry
    {
        $entry = JournalEntry::withTrashed()
            ->where('journal_entry_id', $journalEntryId)
            ->first();

        if (! $entry) {
            throw new DomainException('سند یافت نشد.', 'fin.journal.not_found');
        }

        if (! $entry->trashed()) {
            throw new DomainException('این سند در سطل حذف نیست.', 'fin.journal.not_trashed');
        }

        $this->companyScope->assertCanOperateCompany((string) $entry->company_id);
        $this->assertCanMutateDraft($entry, $actorId);

        $entry->restore();
        $entry->deleted_by = null;
        $entry->row_version = ((int) ($entry->row_version ?? 1)) + 1;
        $entry->save();

        return $entry->fresh(['items']);
    }

    public function find(string $journalEntryId): JournalEntry
    {
        $entry = JournalEntry::with('items')->where('journal_entry_id', $journalEntryId)->first();
        if (! $entry) {
            throw new DomainException('سند یافت نشد.', 'fin.journal.not_found');
        }

        $this->companyScope->assertCanReadCompany((string) $entry->company_id);

        return $entry;
    }

    /**
     * @param  array{company_id?: string, period_id?: string, status?: string, deleted?: bool, limit?: int}  $filters
     * @return Collection<int, JournalEntry>
     */
    public function list(array $filters = []): Collection
    {
        $q = JournalEntry::query()
            ->withSum('items as items_debit_sum', 'debit_amount')
            ->withSum('items as items_credit_sum', 'credit_amount')
            ->orderByDesc('created_at');

        if (! empty($filters['deleted'])) {
            $q->onlyTrashed();
        }

        $companyFilter = isset($filters['company_id']) ? (string) $filters['company_id'] : null;
        $this->companyScope->applyToQuery($q, $companyFilter !== '' ? $companyFilter : null);

        if (! empty($filters['period_id'])) {
            $q->where('period_id', $filters['period_id']);
        }
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        $limit = min(100, max(1, (int) ($filters['limit'] ?? 100)));

        return $q->limit($limit)->get();
    }

    /**
     * @param  array{company_id?: string|null, period_id?: string|null}  $filters
     * @return array{all: int, DRAFT: int, POSTED: int, REVERSED: int, DELETED: int}
     */
    public function statusSummary(array $filters = []): array
    {
        $q = JournalEntry::query();
        $companyFilter = isset($filters['company_id']) ? (string) $filters['company_id'] : null;
        $this->companyScope->applyToQuery($q, $companyFilter !== '' ? $companyFilter : null);

        if (! empty($filters['period_id'])) {
            $q->where('period_id', $filters['period_id']);
        }

        $byStatus = (clone $q)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $deleted = (clone $q)->onlyTrashed()->count();

        $draft = (int) ($byStatus['DRAFT'] ?? 0);
        $posted = (int) ($byStatus['POSTED'] ?? 0);
        $reversed = (int) ($byStatus['REVERSED'] ?? 0);

        return [
            'all' => $draft + $posted + $reversed,
            'DRAFT' => $draft,
            'POSTED' => $posted,
            'REVERSED' => $reversed,
            'DELETED' => (int) $deleted,
        ];
    }

    public function post(string $journalEntryId, ?string $actorId = null): JournalEntry
    {
        $entry = $this->find($journalEntryId);
        $this->companyScope->assertCanOperateCompany((string) $entry->company_id);

        if (! $entry->isDraft()) {
            throw new DomainException(
                'فقط پیش‌نویس قابل ثبت قطعی است.',
                'fin.journal.post_only_draft'
            );
        }

        $this->periodControl->assertPostingAllowed(
            (string) $entry->company_id,
            (string) $entry->period_id
        );

        $items = JournalItem::where('journal_entry_id', $entry->journal_entry_id)->get();
        if ($items->isEmpty()) {
            throw new DomainException('سند بدون سطر قابل ثبت نیست.', 'fin.journal.empty_lines');
        }

        $debit = $items->sum(fn (JournalItem $i) => (float) $i->debit_amount);
        $credit = $items->sum(fn (JournalItem $i) => (float) $i->credit_amount);

        if (round($debit, 4) !== round($credit, 4) || $debit <= 0) {
            throw new DomainException(
                'سند ناتراز است یا مبلغ صفر دارد.',
                'fin.journal.unbalanced'
            );
        }

        foreach ($items as $item) {
            $account = Account::where('account_id', $item->account_id)->first();
            if (! $account || ! $account->is_postable) {
                throw new DomainException(
                    'یکی از حساب‌های سطر قابل ثبت نیست.',
                    'fin.journal.account_not_postable'
                );
            }

            if ($account->requires_cost_center && empty($item->cost_center_id)) {
                throw new DomainException(
                    'برای حساب «'.$account->account_code.'» مرکز هزینه الزامی است.',
                    'fin.journal.cost_center_required'
                );
            }
            if ($account->requires_business_unit && empty($item->business_unit_id)) {
                throw new DomainException(
                    'برای حساب «'.$account->account_code.'» واحد کسب‌وکار الزامی است.',
                    'fin.journal.business_unit_required'
                );
            }
        }

        return DB::transaction(function () use ($entry, $actorId) {
            $fy = $this->resolveFiscalYearKey(
                (string) $entry->period_id,
                (string) $entry->document_date
            );
            $number = $this->nextSequenceNumber(
                (string) $entry->company_id,
                $fy,
                'JOURNAL_JE',
                'JE'
            );

            if (empty($entry->draft_number) && ! empty($entry->entry_number)) {
                $entry->draft_number = $entry->entry_number;
            }

            $entry->entry_number = $number;
            $entry->status = JournalEntry::STATUS_POSTED;
            $entry->posting_date = now();
            $entry->posted_by = $actorId;
            $entry->row_version = ((int) ($entry->row_version ?? 1)) + 1;
            $entry->save();

            $this->events->publish(
                (string) $entry->tenant_id,
                'fin_acc_journal_entries',
                (string) $entry->journal_entry_id,
                FinanceEventPublisher::JOURNAL_POSTED,
                [
                    'journal_entry_id' => (string) $entry->journal_entry_id,
                    'company_id'       => (string) $entry->company_id,
                    'period_id'        => (string) $entry->period_id,
                    'entry_number'     => (string) $entry->entry_number,
                    'draft_number'     => (string) ($entry->draft_number ?? ''),
                ]
            );

            return $entry->fresh(['items']);
        });
    }

    public function reverse(string $journalEntryId, ?string $actorId = null, ?string $description = null): JournalEntry
    {
        $original = $this->find($journalEntryId);
        $this->companyScope->assertCanOperateCompany((string) $original->company_id);

        if (! $original->isPosted()) {
            throw new DomainException(
                'فقط سند ثبت‌شده قابل برگشت است.',
                'fin.journal.reverse_only_posted'
            );
        }

        if ($original->reversed_by_entry_id) {
            throw new DomainException(
                'این سند قبلاً برگشت خورده است.',
                'fin.journal.already_reversed'
            );
        }

        if (! empty($original->reverses_entry_id)) {
            throw new DomainException(
                'سند برگشت قابل برگشت مجدد نیست. برای اصلاح، سند جدید ثبت کنید.',
                'fin.journal.cannot_reverse_reversal'
            );
        }

        $this->periodControl->assertPostingAllowed(
            (string) $original->company_id,
            (string) $original->period_id
        );

        $items = JournalItem::where('journal_entry_id', $original->journal_entry_id)->get();

        return DB::transaction(function () use ($original, $items, $actorId, $description) {
            $fy = $this->resolveFiscalYearKey(
                (string) $original->period_id,
                (string) $original->document_date
            );
            $draftNumber = $this->nextSequenceNumber(
                (string) $original->company_id,
                $fy,
                'JOURNAL_DR',
                'DR'
            );

            $reversal = JournalEntry::create([
                'journal_entry_id'  => (string) Str::uuid(),
                'tenant_id'         => $original->tenant_id,
                'ledger_id'         => $original->ledger_id,
                'company_id'        => $original->company_id,
                'period_id'         => $original->period_id,
                'document_date'     => $original->document_date,
                'status'            => JournalEntry::STATUS_DRAFT,
                'draft_number'      => $draftNumber,
                'entry_number'      => $draftNumber,
                'description'       => $description ?? ('برگشت سند '.$original->entry_number),
                'reverses_entry_id' => $original->journal_entry_id,
                'created_by'        => $actorId,
                'row_version'       => 1,
            ]);

            $lines = [];
            foreach ($items as $item) {
                $lines[] = [
                    'account_id'             => $item->account_id,
                    'debit_amount'           => $item->credit_amount,
                    'credit_amount'          => $item->debit_amount,
                    'description'            => $item->description,
                    'cost_center_id'         => $item->cost_center_id,
                    'business_unit_id'       => $item->business_unit_id,
                    'source_currency_amount' => $item->source_currency_amount,
                ];
            }
            $this->replaceLines($reversal, $lines, (string) $original->tenant_id);

            $posted = $this->post((string) $reversal->journal_entry_id, $actorId);

            $original->status = JournalEntry::STATUS_REVERSED;
            $original->reversed_by_entry_id = $posted->journal_entry_id;
            $original->row_version = ((int) ($original->row_version ?? 1)) + 1;
            $original->save();

            $this->events->publish(
                (string) $original->tenant_id,
                'fin_acc_journal_entries',
                (string) $original->journal_entry_id,
                FinanceEventPublisher::JOURNAL_REVERSED,
                [
                    'journal_entry_id'  => (string) $original->journal_entry_id,
                    'reversal_entry_id' => (string) $posted->journal_entry_id,
                    'company_id'        => (string) $original->company_id,
                ]
            );

            return $posted;
        });
    }

    protected function assertCanMutateDraft(JournalEntry $entry, ?string $actorId): void
    {
        // FIN-HOLD-W1 / L03: mutate draft is operate
        $this->companyScope->assertCanOperateCompany((string) $entry->company_id);

        if ($actorId === null || $actorId === '') {
            throw new DomainException(
                'شناسه کاربر برای این عملیات لازم است.',
                'fin.journal.actor_required'
            );
        }

        $createdBy = (string) ($entry->created_by ?? '');
        if ($createdBy !== '' && $createdBy === $actorId) {
            return;
        }

        $isOwner = DB::table('tenant_users')
            ->where('tenant_id', $entry->tenant_id)
            ->where('user_id', $actorId)
            ->where('is_owner', true)
            ->whereNull('deleted_at')
            ->exists();

        if ($isOwner) {
            return;
        }

        if ($createdBy === '') {
            throw new DomainException(
                'حذف/ویرایش این پیش‌نویس فقط برای مدیر مستأجر مجاز است.',
                'fin.journal.mutate_forbidden'
            );
        }

        throw new DomainException(
            'فقط ایجادکننده پیش‌نویس یا مدیر مستأجر می‌تواند آن را ویرایش یا حذف کند.',
            'fin.journal.mutate_forbidden'
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function replaceLines(JournalEntry $entry, array $lines, string $tenantId): void
    {
        $order = 0;
        foreach ($lines as $line) {
            $debit = (float) ($line['debit_amount'] ?? 0);
            $credit = (float) ($line['credit_amount'] ?? 0);
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }
            $lineAmount = $debit > 0 ? $debit : $credit;
            $sourceAmount = array_key_exists('source_currency_amount', $line) && $line['source_currency_amount'] !== null
                ? (float) $line['source_currency_amount']
                : $lineAmount;

            JournalItem::create([
                'journal_item_id'        => (string) Str::uuid(),
                'tenant_id'              => $tenantId,
                'journal_entry_id'       => $entry->journal_entry_id,
                'account_id'             => $line['account_id'],
                'debit_amount'           => $debit,
                'credit_amount'          => $credit,
                'description'            => $line['description'] ?? null,
                'cost_center_id'         => $line['cost_center_id'] ?? null,
                'business_unit_id'       => $line['business_unit_id'] ?? null,
                'currency_id'            => $line['currency_id'] ?? null,
                'exchange_rate'          => $line['exchange_rate'] ?? 1,
                'source_currency_amount' => $sourceAmount,
                'sort_order'             => $order++,
            ]);
        }
    }

    protected function nextSequenceNumber(
        string $companyId,
        string $fiscalYearKey,
        string $sequenceKey,
        string $defaultPrefix
    ): string {
        $tenantId = $this->requireTenantId();
        $yearKey = $fiscalYearKey !== '' ? $fiscalYearKey : (string) $this->gregorianToJalaliYear(
            (int) date('Y'),
            (int) date('n'),
            (int) date('j')
        );

        $seq = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('sequence_key', $sequenceKey)
            ->where('fiscal_year_key', $yearKey)
            ->lockForUpdate()
            ->first();

        if (! $seq) {
            $seq = DocumentSequence::create([
                'sequence_id'     => (string) Str::uuid(),
                'tenant_id'       => $tenantId,
                'company_id'      => $companyId,
                'sequence_key'    => $sequenceKey,
                'prefix'          => $defaultPrefix,
                'fiscal_year_key' => $yearKey,
                'next_number'     => 1,
                'pad_length'      => 6,
                'row_version'     => 1,
            ]);
            $seq = DocumentSequence::query()
                ->where('sequence_id', $seq->sequence_id)
                ->lockForUpdate()
                ->first();
        }

        $n = (int) $seq->next_number;
        $seq->next_number = $n + 1;
        $seq->row_version = ((int) ($seq->row_version ?? 1)) + 1;
        $seq->save();

        $pad = max(4, (int) ($seq->pad_length ?? 6));
        $prefix = (string) ($seq->prefix ?: $defaultPrefix);

        return sprintf('%s-%s-%0'.$pad.'d', $prefix, $yearKey, $n);
    }

    /**
     * ADR-FIN-002 D6/N03 — prefer company's fiscal period label; fall back to document date Jalali year.
     */
    protected function resolveFiscalYearKey(string $periodId, string $documentDate): string
    {
        if ($periodId !== '') {
            $fromPeriod = $this->fiscalYearFromPeriodId($periodId);
            if ($fromPeriod !== null) {
                return $fromPeriod;
            }
        }

        $gy = (int) date('Y');
        $gm = (int) date('n');
        $gd = (int) date('j');

        if ($documentDate !== '') {
            $ts = strtotime(substr($documentDate, 0, 10));
            if ($ts !== false) {
                $gy = (int) date('Y', $ts);
                $gm = (int) date('n', $ts);
                $gd = (int) date('j', $ts);
            }
        }

        return (string) $this->gregorianToJalaliYear($gy, $gm, $gd);
    }

    /**
     * Resolve FY label from fin_fiscal_periods when table/columns exist (logical period_id).
     */
    protected function fiscalYearFromPeriodId(string $periodId): ?string
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('fin_fiscal_periods')) {
                return null;
            }
            $row = DB::table('fin_fiscal_periods')
                ->where('period_id', $periodId)
                ->whereNull('deleted_at')
                ->first();
            if (! $row) {
                return null;
            }
            foreach (['fiscal_year_key', 'fiscal_year', 'year_label', 'jalali_year'] as $col) {
                if (isset($row->{$col}) && $row->{$col} !== null && $row->{$col} !== '') {
                    $v = (string) $row->{$col};
                    if (preg_match('/^\d{4}$/', $v)) {
                        return $v;
                    }
                    if (preg_match('/(13|14)\d{2}/', $v, $m)) {
                        return $m[0];
                    }
                }
            }
            if (! empty($row->start_date)) {
                $ts = strtotime(substr((string) $row->start_date, 0, 10));
                if ($ts !== false) {
                    return (string) $this->gregorianToJalaliYear(
                        (int) date('Y', $ts),
                        (int) date('n', $ts),
                        (int) date('j', $ts)
                    );
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /** Gregorian Y-M-D → Jalali year only (common PHP port of Caspian algorithm). */
    protected function gregorianToJalaliYear(int $gy, int $gm, int $gd): int
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
        }

        return $jy;
    }

    protected function resolveLeadingLedgerId(string $companyId): string
    {
        if ($companyId === '') {
            throw new DomainException('شناسه شرکت برای انتخاب دفتر کل لازم است.', 'fin.journal.company_required');
        }

        $ledger = Ledger::query()
            ->where('company_id', $companyId)
            ->where('is_leading', true)
            ->first()
            ?? Ledger::query()->where('company_id', $companyId)->orderBy('created_at')->first();

        if (! $ledger) {
            throw new DomainException('دفتر کل پیش‌فرض برای این شرکت یافت نشد.', 'fin.journal.no_ledger');
        }

        return (string) $ledger->ledger_id;
    }

    protected function assertLedger(string $ledgerId): void
    {
        if ($ledgerId === '' || ! Ledger::where('ledger_id', $ledgerId)->exists()) {
            throw new DomainException('دفتر کل نامعتبر است.', 'fin.journal.invalid_ledger');
        }
    }

    protected function requireTenantId(): string
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (! $tenantId) {
            throw new DomainException('بافت مستأجر تنظیم نشده است.', 'fin.tenant_missing');
        }

        return $tenantId;
    }
}
