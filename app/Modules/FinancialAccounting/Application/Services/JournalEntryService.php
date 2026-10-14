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
 * Dual series: DR/JE. Access: read vs operate split on FinanceCompanyScope.
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

        $this->companyScope->assertCanAccessCompany((string) $entry->company_id);

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

    protected function replaceLines(JournalEntry $entry, array $lines, string $tenantId): void
    {
        $order = 0;
        foreach ($lines as $line) {
            $order++;
            JournalItem::create([
                'journal_item_id'        => (string) Str::uuid(),
                'tenant_id'              => $tenantId,
                'journal_entry_id'       => $entry->journal_entry_id,
                'account_id'             => $line['account_id'],
                'line_order'             => $order,
                'debit_amount'           => $line['debit_amount'] ?? 0,
                'credit_amount'          => $line['credit_amount'] ?? 0,
                'description'            => $line['description'] ?? null,
                'cost_center_id'         => $line['cost_center_id'] ?? null,
                'business_unit_id'       => $line['business_unit_id'] ?? null,
                'source_currency_amount' => $line['source_currency_amount'] ?? null,
            ]);
        }
    }

    protected function assertLedger(string $ledgerId): void
    {
        $ledger = Ledger::where('ledger_id', $ledgerId)->first();
        if (! $ledger) {
            throw new DomainException('دفتر یافت نشد.', 'fin.ledger.not_found');
        }
    }

    protected function resolveLeadingLedgerId(string $companyId): string
    {
        $id = Ledger::query()
            ->where('company_id', $companyId)
            ->where('is_leading', true)
            ->whereNull('deleted_at')
            ->value('ledger_id');

        if (! $id) {
            throw new DomainException('دفتر اصلی شرکت یافت نشد.', 'fin.ledger.leading_missing');
        }

        return (string) $id;
    }

    protected function resolveFiscalYearKey(string $periodId, string $documentDate): string
    {
        $fromPeriod = DB::table('fin_fiscal_periods')
            ->where('period_id', $periodId)
            ->value('fiscal_year');

        if ($fromPeriod) {
            return (string) $fromPeriod;
        }

        if ($documentDate !== '') {
            return substr($documentDate, 0, 4);
        }

        return (string) date('Y');
    }

    protected function nextSequenceNumber(string $companyId, string $fy, string $kind, string $prefix): string
    {
        $tenantId = $this->requireTenantId();
        $row = DocumentSequence::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('fiscal_year', $fy)
            ->where('kind', $kind)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            $row = DocumentSequence::create([
                'document_sequence_id' => (string) Str::uuid(),
                'tenant_id'            => $tenantId,
                'company_id'           => $companyId,
                'fiscal_year'          => $fy,
                'kind'                 => $kind,
                'last_number'          => 0,
            ]);
            $row = DocumentSequence::query()
                ->where('document_sequence_id', $row->document_sequence_id)
                ->lockForUpdate()
                ->first();
        }

        $next = ((int) $row->last_number) + 1;
        $row->last_number = $next;
        $row->save();

        return sprintf('%s-%s-%06d', $prefix, $fy, $next);
    }

    protected function requireTenantId(): string
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (! $tenantId) {
            throw new DomainException('Tenant Context is missing.', 'fin.tenant_missing');
        }

        return $tenantId;
    }
}
