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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FIN-P0-12..14 — Draft CRUD, post (balanced + numbered), reverse.
 * FIN-P3-06 — outbox finance.journal.posted.v1 / reversed.v1 on post/reverse.
 * FIN-P4-04 — required analytical dimensions on post when account flagged.
 */
class JournalEntryService
{
    public function __construct(
        protected FiscalPeriodControlService $periodControl = new FiscalPeriodControlService(),
        protected FinanceEventPublisher $events = new FinanceEventPublisher()
    ) {
    }

    /**
     * @param  array{
     *   ledger_id: string,
     *   company_id: string,
     *   period_id: string,
     *   document_date: string,
     *   description?: string|null,
     *   source_document_type?: string|null,
     *   source_document_id?: string|null,
     *   lines: list<array{
     *     account_id: string,
     *     debit_amount?: float|string,
     *     credit_amount?: float|string,
     *     description?: string|null,
     *     cost_center_id?: string|null,
     *     business_unit_id?: string|null,
     *     currency_id?: string|null,
     *     exchange_rate?: float|string,
     *     source_currency_amount?: float|string
     *   }>
     * }  $payload
     */
    public function createDraft(array $payload): JournalEntry
    {
        $tenantId = $this->requireTenantId();
        $this->assertLedger($payload['ledger_id'] ?? '');

        return DB::transaction(function () use ($tenantId, $payload) {
            $entry = JournalEntry::create([
                'journal_entry_id'      => (string) Str::uuid(),
                'tenant_id'             => $tenantId,
                'ledger_id'             => $payload['ledger_id'],
                'company_id'            => $payload['company_id'],
                'period_id'             => $payload['period_id'],
                'document_date'         => $payload['document_date'],
                'status'                => JournalEntry::STATUS_DRAFT,
                'description'           => $payload['description'] ?? null,
                'source_document_type'  => $payload['source_document_type'] ?? null,
                'source_document_id'    => $payload['source_document_id'] ?? null,
                'row_version'           => 1,
            ]);

            $this->replaceLines($entry, $payload['lines'] ?? [], $tenantId);

            return $entry->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateDraft(string $journalEntryId, array $payload): JournalEntry
    {
        $entry = $this->find($journalEntryId);

        if (! $entry->isDraft()) {
            throw new DomainException(
                'فقط پیش‌نویس قابل ویرایش است.',
                'fin.journal.not_draft'
            );
        }

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

    public function deleteDraft(string $journalEntryId): void
    {
        $entry = $this->find($journalEntryId);

        if (! $entry->isDraft()) {
            throw new DomainException(
                'فقط پیش‌نویس قابل حذف است. سند قطعی را برگشت بزنید.',
                'fin.journal.delete_only_draft'
            );
        }

        DB::transaction(function () use ($entry) {
            JournalItem::where('journal_entry_id', $entry->journal_entry_id)->delete();
            $entry->delete();
        });
    }

    public function post(string $journalEntryId, ?string $actorId = null): JournalEntry
    {
        $entry = $this->find($journalEntryId);

        if (! $entry->isDraft()) {
            throw new DomainException('فقط پیش‌نویس قابل ثبت قطعی است.', 'fin.journal.post_only_draft');
        }

        $this->periodControl->assertPostingAllowed(
            (string) $entry->company_id,
            (string) $entry->period_id
        );

        $items = JournalItem::where('journal_entry_id', $entry->journal_entry_id)->get();
        if ($items->isEmpty()) {
            throw new DomainException('سند بدون آرتیکل قابل ثبت نیست.', 'fin.journal.empty_lines');
        }

        $debit = $items->sum(fn (JournalItem $i) => (float) $i->debit_amount);
        $credit = $items->sum(fn (JournalItem $i) => (float) $i->credit_amount);

        if (round($debit, 4) !== round($credit, 4) || $debit <= 0) {
            throw new DomainException(
                'سند نامتراز است یا مبلغ صفر دارد.',
                'fin.journal.unbalanced'
            );
        }

        foreach ($items as $item) {
            $account = Account::where('account_id', $item->account_id)->first();
            if (! $account || ! $account->is_postable) {
                throw new DomainException(
                    'یکی از حساب‌های آرتیکل قابل ثبت نیست.',
                    'fin.journal.account_not_postable'
                );
            }

            // FIN-P4-04 dimension gates
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
            $number = $this->nextEntryNumber(
                (string) $entry->company_id,
                (string) $entry->period_id
            );

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
                    'journal_entry_id' => $entry->journal_entry_id,
                    'company_id'       => $entry->company_id,
                    'period_id'        => $entry->period_id,
                    'ledger_id'        => $entry->ledger_id,
                    'entry_number'     => $entry->entry_number,
                    'posted_by'        => $actorId,
                ]
            );

            return $entry->fresh(['items']);
        });
    }

    public function reverse(string $journalEntryId, ?string $actorId = null, ?string $description = null): JournalEntry
    {
        $original = $this->find($journalEntryId);

        if (! $original->isPosted()) {
            throw new DomainException('فقط سند ثبت‌شده قابل برگشت است.', 'fin.journal.reverse_only_posted');
        }

        if ($original->reversed_by_entry_id) {
            throw new DomainException('این سند قبلاً برگشت خورده است.', 'fin.journal.already_reversed');
        }

        $this->periodControl->assertPostingAllowed(
            (string) $original->company_id,
            (string) $original->period_id
        );

        return DB::transaction(function () use ($original, $actorId, $description) {
            $tenantId = (string) $original->tenant_id;

            $reverse = JournalEntry::create([
                'journal_entry_id'     => (string) Str::uuid(),
                'tenant_id'            => $tenantId,
                'ledger_id'            => $original->ledger_id,
                'company_id'           => $original->company_id,
                'period_id'            => $original->period_id,
                'document_date'        => now()->toDateString(),
                'status'               => JournalEntry::STATUS_DRAFT,
                'description'          => $description ?? ('برگشت سند '.$original->entry_number),
                'reverses_entry_id'    => $original->journal_entry_id,
                'row_version'          => 1,
            ]);

            $lines = [];
            foreach ($original->items as $item) {
                $lines[] = [
                    'account_id'             => $item->account_id,
                    'debit_amount'           => (float) $item->credit_amount,
                    'credit_amount'          => (float) $item->debit_amount,
                    'description'            => $item->description,
                    'cost_center_id'         => $item->cost_center_id,
                    'business_unit_id'       => $item->business_unit_id,
                    'currency_id'            => $item->currency_id,
                    'exchange_rate'          => $item->exchange_rate,
                    'source_currency_amount' => $item->source_currency_amount,
                ];
            }

            $this->replaceLines($reverse, $lines, $tenantId);

            $posted = $this->post($reverse->journal_entry_id, $actorId);

            $original->status = JournalEntry::STATUS_REVERSED;
            $original->reversed_by_entry_id = $posted->journal_entry_id;
            $original->row_version = ((int) ($original->row_version ?? 1)) + 1;
            $original->save();

            $this->events->publish(
                $tenantId,
                'fin_acc_journal_entries',
                (string) $original->journal_entry_id,
                FinanceEventPublisher::JOURNAL_REVERSED,
                [
                    'original_journal_entry_id'  => $original->journal_entry_id,
                    'reversing_journal_entry_id' => $posted->journal_entry_id,
                    'company_id'                 => $original->company_id,
                    'period_id'                  => $original->period_id,
                ]
            );

            return $posted->fresh(['items']);
        });
    }

    public function find(string $journalEntryId): JournalEntry
    {
        return JournalEntry::with('items')->where('journal_entry_id', $journalEntryId)->firstOrFail();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function replaceLines(JournalEntry $entry, array $lines, string $tenantId): void
    {
        $sort = 0;
        foreach ($lines as $line) {
            $debit = round((float) ($line['debit_amount'] ?? 0), 4);
            $credit = round((float) ($line['credit_amount'] ?? 0), 4);

            if ($debit > 0 && $credit > 0) {
                throw new DomainException(
                    'هر آرتیکل فقط بدهکار یا بستانکار می‌تواند باشد.',
                    'fin.journal.line_both_sides'
                );
            }

            $accountId = (string) ($line['account_id'] ?? '');
            if ($accountId === '' || ! Account::where('account_id', $accountId)->exists()) {
                throw new DomainException('حساب آرتیکل نامعتبر است.', 'fin.journal.invalid_account');
            }

            JournalItem::create([
                'journal_item_id'        => (string) Str::uuid(),
                'journal_entry_id'       => $entry->journal_entry_id,
                'tenant_id'              => $tenantId,
                'account_id'             => $accountId,
                'cost_center_id'         => $line['cost_center_id'] ?? null,
                'business_unit_id'       => $line['business_unit_id'] ?? null,
                'debit_amount'           => $debit,
                'credit_amount'          => $credit,
                'currency_id'            => $line['currency_id'] ?? null,
                'exchange_rate'          => $line['exchange_rate'] ?? 1,
                'source_currency_amount' => $line['source_currency_amount'] ?? 0,
                'description'            => $line['description'] ?? null,
                'sort_order'             => $sort++,
                'created_at'             => now(),
            ]);
        }
    }

    protected function nextEntryNumber(string $companyId, string $periodId): string
    {
        $tenantId = $this->requireTenantId();
        $yearKey = substr(str_replace('-', '', $periodId), 0, 8) ?: date('Y');

        $seq = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('sequence_key', 'JOURNAL')
            ->where('fiscal_year_key', $yearKey)
            ->lockForUpdate()
            ->first();

        if (! $seq) {
            $seq = DocumentSequence::create([
                'sequence_id'     => (string) Str::uuid(),
                'tenant_id'       => $tenantId,
                'company_id'      => $companyId,
                'sequence_key'    => 'JOURNAL',
                'prefix'          => 'JE',
                'fiscal_year_key' => $yearKey,
                'next_number'     => 1,
                'pad_length'      => 6,
                'row_version'     => 1,
            ]);
            $seq = DocumentSequence::where('sequence_id', $seq->sequence_id)->lockForUpdate()->first();
        }

        $n = (int) $seq->next_number;
        $seq->next_number = $n + 1;
        $seq->row_version = ((int) ($seq->row_version ?? 1)) + 1;
        $seq->save();

        $prefix = $seq->prefix ?: 'JE';
        $pad = max(1, (int) $seq->pad_length);

        return $prefix.'-'.str_pad((string) $n, $pad, '0', STR_PAD_LEFT);
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
