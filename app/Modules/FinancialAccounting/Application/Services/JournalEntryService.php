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
 * FIN-P0-12..14 + ADR-FIN-002 + FIN-HOLD-W1 + FIN-HOLD-W3/L07 + FIN-HOLD-W6/L15
 * Dual series DR/JE; optional branch series via CompanyNumberingService.
 */
class JournalEntryService
{
    public function __construct(
        protected FiscalPeriodControlService $periodControl = new FiscalPeriodControlService(),
        protected FinanceEventPublisher $events = new FinanceEventPublisher(),
        protected FinanceCompanyScope $companyScope = new FinanceCompanyScope()
    ) {
    }

    public function createDraft(array $payload, ?string $actorId = null): JournalEntry
    {
        $tenantId = $this->requireTenantId();
        $companyId = (string) ($payload['company_id'] ?? '');
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
            $branchId = isset($payload['branch_id']) && $payload['branch_id'] !== ''
                ? (string) $payload['branch_id']
                : null;
            $draftNumber = $this->nextSequenceNumber(
                $companyId,
                $fy,
                'JOURNAL_DR',
                'DR',
                $branchId
            );

            $entry = JournalEntry::create([
                'journal_entry_id'     => (string) Str::uuid(),
                'tenant_id'            => $tenantId,
                'ledger_id'            => $payload['ledger_id'],
                'company_id'           => $companyId,
                'branch_id'            => $branchId,
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

    public function updateDraft(string $journalEntryId, array $payload, ?string $actorId = null): JournalEntry
    {
        $entry = $this->find($journalEntryId);
        if (! $entry->isDraft()) {
            throw new DomainException('فقط پیش‌نویس قابل ویرایش است.', 'fin.journal.not_draft');
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
            throw new DomainException('فقط پیش‌نویس قابل حذف است. سند قطعی را برگشت بزنید.', 'fin.journal.delete_only_draft');
        }
        $this->assertCanMutateDraft($entry, $actorId);
        DB::transaction(function () use ($entry, $actorId) {
            $entry->deleted_by = $actorId;
            $entry->row_version = ((int) ($entry->row_version ?? 1)) + 1;
            $entry->save();
            $entry->delete();
            $this->events->publish(
                (string) $entry->tenant_id,
                'fin_acc_journal_entries',
                (string) $entry->journal_entry_id,
                FinanceEventPublisher::JOURNAL_DELETED,
                [
                    'journal_entry_id' => (string) $entry->journal_entry_id,
                    'company_id' => (string) $entry->company_id,
                    'actor_id' => $actorId,
                    'status_was' => 'DRAFT',
                ]
            );
        });
    }

    public function restoreDraft(string $journalEntryId, ?string $actorId = null): JournalEntry
    {
        $entry = JournalEntry::withTrashed()->where('journal_entry_id', $journalEntryId)->first();
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

    public function statusSummary(array $filters = []): array
    {
        $q = JournalEntry::query();
        $companyFilter = isset($filters['company_id']) ? (string) $filters['company_id'] : null;
        $this->companyScope->applyToQuery($q, $companyFilter !== '' ? $companyFilter : null);
        if (! empty($filters['period_id'])) {
            $q->where('period_id', $filters['period_id']);
        }
        $byStatus = (clone $q)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
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
            throw new DomainException('فقط پیش‌نویس قابل ثبت قطعی است.', 'fin.journal.post_only_draft');
        }
        $this->periodControl->assertPostingAllowed((string) $entry->company_id, (string) $entry->period_id);

        return DB::transaction(function () use ($entry, $actorId) {
            $entry = JournalEntry::query()->where('journal_entry_id', $entry->journal_entry_id)->lockForUpdate()->firstOrFail();
            if (! $entry->isDraft()) {
                throw new DomainException('فقط پیش‌نویس قابل ثبت قطعی است.', 'fin.journal.post_only_draft');
            }
            $items = JournalItem::query()->where('journal_entry_id', $entry->journal_entry_id)->get();
            $debit = $items->sum(fn ($i) => (float) $i->debit_amount);
            $credit = $items->sum(fn ($i) => (float) $i->credit_amount);
            if (abs($debit - $credit) > 0.0001) {
                throw new DomainException('سند نامتوازن است.', 'fin.journal.unbalanced');
            }
            $fy = $this->resolveFiscalYearKey((string) $entry->period_id, (string) $entry->document_date);
            $branchId = $entry->branch_id ? (string) $entry->branch_id : null;
            $entryNumber = $this->nextSequenceNumber(
                (string) $entry->company_id,
                $fy,
                'JOURNAL_JE',
                'JE',
                $branchId
            );
            $entry->entry_number = $entryNumber;
            $entry->status = JournalEntry::STATUS_POSTED;
            $entry->posting_date = $entry->document_date;
            $entry->row_version = ((int) ($entry->row_version ?? 1)) + 1;
            $entry->save();
            $this->events->publish(
                (string) $entry->tenant_id,
                'fin_acc_journal_entries',
                (string) $entry->journal_entry_id,
                FinanceEventPublisher::JOURNAL_POSTED,
                [
                    'journal_entry_id' => (string) $entry->journal_entry_id,
                    'entry_number' => (string) $entry->entry_number,
                    'company_id' => (string) $entry->company_id,
                    'actor_id' => $actorId,
                ]
            );

            return $entry->fresh(['items']);
        });
    }

    public function reverse(string $journalEntryId, ?string $actorId = null, ?string $description = null): JournalEntry
    {
        $original = $this->find($journalEntryId);
        $this->companyScope->assertCanOperateCompany((string) $original->company_id);
        if ($original->status !== JournalEntry::STATUS_POSTED) {
            throw new DomainException('فقط سند قطعی قابل برگشت است.', 'fin.journal.reverse_only_posted');
        }
        if ($original->reversed_by_entry_id) {
            throw new DomainException('این سند قبلاً برگشت خورده است.', 'fin.journal.already_reversed');
        }

        return DB::transaction(function () use ($original, $actorId, $description) {
            $items = JournalItem::query()->where('journal_entry_id', $original->journal_entry_id)->get();
            $fy = $this->resolveFiscalYearKey((string) $original->period_id, (string) $original->document_date);
            $branchId = $original->branch_id ? (string) $original->branch_id : null;
            $draftNumber = $this->nextSequenceNumber(
                (string) $original->company_id,
                $fy,
                'JOURNAL_DR',
                'DR',
                $branchId
            );
            $reversal = JournalEntry::create([
                'journal_entry_id'     => (string) Str::uuid(),
                'tenant_id'            => $original->tenant_id,
                'ledger_id'            => $original->ledger_id,
                'company_id'           => $original->company_id,
                'branch_id'            => $original->branch_id,
                'period_id'            => $original->period_id,
                'document_date'        => $original->document_date,
                'status'               => JournalEntry::STATUS_DRAFT,
                'draft_number'         => $draftNumber,
                'entry_number'         => $draftNumber,
                'description'          => $description ?? ('برگشت سند '.$original->entry_number),
                'reverses_entry_id'    => $original->journal_entry_id,
                'created_by'           => $actorId,
                'row_version'          => 1,
            ]);
            $lines = [];
            foreach ($items as $item) {
                $lines[] = [
                    'account_id' => $item->account_id,
                    'debit_amount' => $item->credit_amount,
                    'credit_amount' => $item->debit_amount,
                    'description' => $item->description,
                    'cost_center_id' => $item->cost_center_id,
                    'business_unit_id' => $item->business_unit_id,
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
                    'journal_entry_id' => (string) $original->journal_entry_id,
                    'reversal_entry_id' => (string) $posted->journal_entry_id,
                    'company_id' => (string) $original->company_id,
                ]
            );

            return $posted;
        });
    }

    protected function assertCanMutateDraft(JournalEntry $entry, ?string $actorId): void
    {
        $this->companyScope->assertCanOperateCompany((string) $entry->company_id);
        if ($actorId === null || $actorId === '') {
            throw new DomainException('شناسه کاربر برای این عملیات لازم است.', 'fin.journal.actor_required');
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
        throw new DomainException('فقط ایجادکننده پیش‌نویس یا مالک فنی می‌تواند آن را تغییر دهد.', 'fin.journal.mutate_forbidden');
    }

    protected function replaceLines(JournalEntry $entry, array $lines, string $tenantId): void
    {
        $order = 0;
        foreach ($lines as $line) {
            $debit = (float) ($line['debit_amount'] ?? 0);
            $credit = (float) ($line['credit_amount'] ?? 0);
            $src = $line['source_currency_amount'] ?? ($debit > 0 ? $debit : $credit);
            JournalItem::create([
                'journal_item_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'journal_entry_id' => $entry->journal_entry_id,
                'account_id' => $line['account_id'],
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'description' => $line['description'] ?? null,
                'cost_center_id' => $line['cost_center_id'] ?? null,
                'business_unit_id' => $line['business_unit_id'] ?? null,
                'source_currency_amount' => round((float) $src, 4),
                'sort_order' => $order++,
            ]);
        }
    }

    protected function nextSequenceNumber(
        string $companyId,
        string $fiscalYearKey,
        string $sequenceKey,
        string $defaultPrefix,
        ?string $branchId = null
    ): string {
        $tenantId = $this->requireTenantId();
        $yearKey = $fiscalYearKey !== '' ? $fiscalYearKey : (string) $this->gregorianToJalaliYear(
            (int) date('Y'),
            (int) date('n'),
            (int) date('j')
        );
        $useBranch = (new CompanyNumberingService($this->companyScope))
            ->shouldUseBranchSeries($companyId, $yearKey, $branchId);
        $seqBranchId = $useBranch ? $branchId : null;
        $q = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('sequence_key', $sequenceKey)
            ->where('fiscal_year_key', $yearKey);
        if ($seqBranchId === null) {
            $q->whereNull('branch_id');
        } else {
            $q->where('branch_id', $seqBranchId);
        }
        $seq = $q->lockForUpdate()->first();
        if (! $seq) {
            $seq = DocumentSequence::create([
                'sequence_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $seqBranchId,
                'sequence_key' => $sequenceKey,
                'prefix' => $defaultPrefix,
                'fiscal_year_key' => $yearKey,
                'next_number' => 1,
                'pad_length' => 6,
                'row_version' => 1,
            ]);
            $seq = DocumentSequence::query()->where('sequence_id', $seq->sequence_id)->lockForUpdate()->first();
        }
        $n = (int) $seq->next_number;
        $seq->next_number = $n + 1;
        $seq->row_version = ((int) ($seq->row_version ?? 1)) + 1;
        $seq->save();
        $pad = max(4, (int) ($seq->pad_length ?? 6));
        $prefix = (string) ($seq->prefix ?: $defaultPrefix);

        return $prefix.'-'.str_pad((string) $n, $pad, '0', STR_PAD_LEFT);
    }

    protected function resolveFiscalYearKey(string $periodId, string $documentDate): string
    {
        $fromPeriod = $this->fiscalYearFromPeriodId($periodId);
        if ($fromPeriod) {
            return $fromPeriod;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $documentDate, $m)) {
            return (string) $this->gregorianToJalaliYear((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return (string) $this->gregorianToJalaliYear((int) date('Y'), (int) date('n'), (int) date('j'));
    }

    protected function fiscalYearFromPeriodId(string $periodId): ?string
    {
        if ($periodId === '' || ! \Illuminate\Support\Facades\Schema::hasTable('fin_fiscal_periods')) {
            return null;
        }
        $row = DB::table('fin_fiscal_periods')->where('period_id', $periodId)->first();
        if (! $row) {
            return null;
        }
        foreach (['fiscal_year_key', 'fiscal_year', 'year_label', 'jalali_year'] as $col) {
            if (isset($row->$col) && (string) $row->$col !== '') {
                return (string) $row->$col;
            }
        }

        return null;
    }

    protected function gregorianToJalaliYear(int $gy, int $gm, int $gd): int
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
        }

        return $jy;
    }

    protected function assertLedger(string $ledgerId): void
    {
        if (! Ledger::where('ledger_id', $ledgerId)->exists()) {
            throw new DomainException('دفتر کل نامعتبر است.', 'fin.journal.invalid_ledger');
        }
    }

    protected function resolveLeadingLedgerId(string $companyId): string
    {
        $ledger = Ledger::query()->where('company_id', $companyId)->where('is_leading', true)->first()
            ?? Ledger::query()->where('company_id', $companyId)->orderBy('created_at')->first();
        if (! $ledger) {
            throw new DomainException('دفتر کل پیش‌فرض برای شرکت یافت نشد.', 'fin.journal.no_ledger');
        }

        return (string) $ledger->ledger_id;
    }

    protected function requireTenantId(): string
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (! $tenantId) {
            throw new DomainException('بافت مستأجر الزامی است.', 'fin.tenant_required');
        }

        return (string) $tenantId;
    }
}
