<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\DocumentSequence;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalItem;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Journal numbers LOCKED: {DR|JE}-{REF3}-{YY}-{#####}
 * REF = company.ref_code (no branch) or branch.ref_code (with branch).
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

        return [
            'all' => (int) ($byStatus['DRAFT'] ?? 0) + (int) ($byStatus['POSTED'] ?? 0) + (int) ($byStatus['REVERSED'] ?? 0),
            'DRAFT' => (int) ($byStatus['DRAFT'] ?? 0),
            'POSTED' => (int) ($byStatus['POSTED'] ?? 0),
            'REVERSED' => (int) ($byStatus['REVERSED'] ?? 0),
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

            return $posted;
        });
    }

    protected function assertCanMutateDraft(JournalEntry $entry, ?string $actorId = null): void
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

    /** Format: {DR|JE}-{REF3}-{YY}-{#####} */
    protected function nextSequenceNumber(
        string $companyId,
        string $fiscalYearKey,
        string $sequenceKey,
        string $defaultPrefix,
        ?string $branchId = null
    ): string {
        $tenantId = $this->requireTenantId();
        $yearKey = $fiscalYearKey !== '' ? $fiscalYearKey : (string) $this->gregorianToJalaliYear((int) date('Y'), (int) date('n'), (int) date('j'));
        $yy = strlen($yearKey) >= 2 ? substr($yearKey, -2) : str_pad($yearKey, 2, '0', STR_PAD_LEFT);
        $refCode = $this->resolveRefCode($companyId, $branchId, $tenantId);
        $seqBranchId = ($branchId !== null && $branchId !== '') ? $branchId : null;

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
                'pad_length' => 5,
                'row_version' => 1,
            ]);
            $seq = DocumentSequence::query()->where('sequence_id', $seq->sequence_id)->lockForUpdate()->first();
        }

        $n = max(1, (int) $seq->next_number);
        $maxUsed = $this->maxUsedSerial($companyId, $defaultPrefix, $refCode, $yy, $seqBranchId);
        $n = max($n, $maxUsed + 1);
        $seq->next_number = $n + 1;
        $seq->row_version = ((int) ($seq->row_version ?? 1)) + 1;
        $seq->save();

        return $defaultPrefix.'-'.$refCode.'-'.$yy.'-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    protected function resolveRefCode(string $companyId, ?string $branchId, string $tenantId): string
    {
        if ($branchId !== null && $branchId !== '') {
            $row = DB::table('erp_branches')->where('branch_id', $branchId)->first();
            if ($row && ! empty($row->ref_code)) {
                return strtoupper((string) $row->ref_code);
            }
            $code = $this->allocateRefCode($tenantId, (string) ($row->code ?? ''), (string) ($row->name ?? 'BR'), 'B');
            if ($row) {
                DB::table('erp_branches')->where('branch_id', $branchId)->update(['ref_code' => $code]);
            }

            return $code;
        }

        $row = DB::table('erp_companies')->where('company_id', $companyId)->first();
        if ($row && ! empty($row->ref_code)) {
            return strtoupper((string) $row->ref_code);
        }
        $code = $this->allocateRefCode($tenantId, (string) ($row->code ?? ''), (string) ($row->name ?? 'CO'), 'C');
        if ($row) {
            DB::table('erp_companies')->where('company_id', $companyId)->update(['ref_code' => $code]);
        }

        return $code;
    }

    protected function allocateRefCode(string $tenantId, string $code, string $name, string $fallbackPrefix): string
    {
        $used = [];
        foreach (DB::table('erp_companies')->where('tenant_id', $tenantId)->whereNotNull('ref_code')->pluck('ref_code') as $c) {
            $used[strtoupper((string) $c)] = true;
        }
        foreach (DB::table('erp_branches')->where('tenant_id', $tenantId)->whereNotNull('ref_code')->pluck('ref_code') as $c) {
            $used[strtoupper((string) $c)] = true;
        }
        $fromCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($fromCode) >= 3) {
            $cand = substr($fromCode, 0, 3);
            if (! isset($used[$cand])) {
                return $cand;
            }
        }
        if (strlen($fromCode) > 0 && strlen($fromCode) < 3) {
            $cand = str_pad($fromCode, 3, '0');
            if (! isset($used[$cand])) {
                return $cand;
            }
        }
        for ($i = 1; $i < 1000; $i++) {
            $cand = $fallbackPrefix.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            if (! isset($used[$cand])) {
                return $cand;
            }
        }

        return $fallbackPrefix.strtoupper(substr(md5($name.$code), 0, 2));
    }

    protected function maxUsedSerial(string $companyId, string $typePrefix, string $refCode, string $yy, ?string $branchId): int
    {
        $needle = $typePrefix.'-'.$refCode.'-'.$yy.'-';
        $q = JournalEntry::withTrashed()->where('company_id', $companyId);
        if ($branchId !== null && $branchId !== '') {
            $q->where('branch_id', $branchId);
        } else {
            $q->whereNull('branch_id');
        }
        $max = 0;
        foreach ($q->get(['entry_number', 'draft_number']) as $row) {
            foreach ([(string) ($row->entry_number ?? ''), (string) ($row->draft_number ?? '')] as $num) {
                if ($num !== '' && str_starts_with($num, $needle)) {
                    $suffix = substr($num, strlen($needle));
                    if (ctype_digit($suffix)) {
                        $max = max($max, (int) $suffix);
                    }
                }
            }
        }

        return $max;
    }

    protected function resolveFiscalYearKey(string $periodId, string $documentDate): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $documentDate, $m)) {
            return (string) $this->gregorianToJalaliYear((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return (string) $this->gregorianToJalaliYear((int) date('Y'), (int) date('n'), (int) date('j'));
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

    protected function resolveLeadingLedgerId(string $companyId): string
    {
        $ledger = Ledger::query()->where('company_id', $companyId)->where('is_leading', true)->where('status', 1)->orderBy('code')->first();
        if (! $ledger) {
            $ledger = Ledger::query()->where('company_id', $companyId)->where('status', 1)->orderBy('code')->first();
        }
        if (! $ledger) {
            throw new DomainException('دفتر کل پیش‌فرض برای شرکت یافت نشد.', 'fin.journal.ledger_missing');
        }

        return (string) $ledger->ledger_id;
    }

    protected function assertLedger(string $ledgerId): void
    {
        if (! Ledger::query()->where('ledger_id', $ledgerId)->exists()) {
            throw new DomainException('دفتر کل نامعتبر است.', 'fin.journal.ledger_invalid');
        }
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
