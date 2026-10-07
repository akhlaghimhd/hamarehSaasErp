<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\SmartActionLog;
use App\Modules\FinancialAccounting\Infrastructure\Models\SuggestedJournal;
use App\Modules\FinancialAccounting\Infrastructure\Models\SuggestedJournalLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FIN-P3-03..05 K1 — Build suggested draft from operational amounts; accept → real journal DRAFT only; reject audited.
 * Never posts automatically.
 */
class SuggestedJournalService
{
    public function __construct(
        protected AccountDeterminationService $determination = new AccountDeterminationService(),
        protected JournalEntryService $journals = new JournalEntryService()
    ) {
    }

    /**
     * @param  array{
     *   company_id: string,
     *   ledger_id: string,
     *   period_id: string,
     *   source_event_type: string,
     *   source_document_id: string,
     *   document_date?: string,
     *   description?: string,
     *   amounts: array<string, float|int|string>  // line_role => amount (absolute)
     * }  $payload
     */
    public function suggestFromOperational(array $payload): SuggestedJournal
    {
        $tenantId = $this->requireTenantId();
        $event = (string) $payload['source_event_type'];
        $companyId = (string) $payload['company_id'];

        $lines = [];
        $sort = 0;
        foreach ($payload['amounts'] as $role => $amount) {
            $amt = round((float) $amount, 4);
            if ($amt <= 0) {
                continue;
            }
            $resolved = $this->determination->resolve($event, (string) $role, $companyId);
            [$debit, $credit] = $this->sideForRole((string) $role, $amt);
            $lines[] = [
                'account_id'         => $resolved['account_id'],
                'debit_amount'       => $debit,
                'credit_amount'      => $credit,
                'line_role'          => (string) $role,
                'suggestion_reason'  => $resolved['reason'],
                'sort_order'         => $sort++,
            ];
        }

        if ($lines === []) {
            throw new DomainException('هیچ سطری برای پیشنهاد ساخته نشد.', 'fin.suggest.empty');
        }

        return DB::transaction(function () use ($tenantId, $payload, $lines) {
            $hdr = SuggestedJournal::create([
                'suggested_journal_id' => (string) Str::uuid(),
                'tenant_id'            => $tenantId,
                'company_id'           => $payload['company_id'],
                'ledger_id'            => $payload['ledger_id'],
                'period_id'            => $payload['period_id'],
                'source_event_type'    => $payload['source_event_type'],
                'source_document_id'   => $payload['source_document_id'],
                'status'               => SuggestedJournal::STATUS_PENDING,
                'description'          => $payload['description'] ?? ('پیشنهاد از '.$payload['source_event_type']),
                'row_version'          => 1,
            ]);

            foreach ($lines as $line) {
                SuggestedJournalLine::create([
                    'suggested_line_id'    => (string) Str::uuid(),
                    'suggested_journal_id' => $hdr->suggested_journal_id,
                    'tenant_id'            => $tenantId,
                    'account_id'           => $line['account_id'],
                    'debit_amount'         => $line['debit_amount'],
                    'credit_amount'        => $line['credit_amount'],
                    'line_role'            => $line['line_role'],
                    'suggestion_reason'    => $line['suggestion_reason'],
                    'sort_order'           => $line['sort_order'],
                    'created_at'           => now(),
                ]);
            }

            $this->log('SUGGEST', 'SUGGESTED_JOURNAL', (string) $hdr->suggested_journal_id, null, [
                'source' => $payload['source_event_type'],
                'lines'  => count($lines),
            ]);

            return $hdr->fresh(['lines']);
        });
    }

    public function accept(string $suggestedJournalId, ?string $actorId = null, ?string $note = null): SuggestedJournal
    {
        $sug = SuggestedJournal::with('lines')->where('suggested_journal_id', $suggestedJournalId)->firstOrFail();

        if (! $sug->isPending()) {
            throw new DomainException('این پیشنهاد قبلاً تصمیم‌گیری شده است.', 'fin.suggest.not_pending');
        }

        return DB::transaction(function () use ($sug, $actorId, $note) {
            $journalLines = [];
            foreach ($sug->lines as $line) {
                $journalLines[] = [
                    'account_id'    => $line->account_id,
                    'debit_amount'  => (float) $line->debit_amount,
                    'credit_amount' => (float) $line->credit_amount,
                    'description'   => $line->suggestion_reason,
                ];
            }

            $draft = $this->journals->createDraft([
                'ledger_id'            => $sug->ledger_id,
                'company_id'           => $sug->company_id,
                'period_id'            => $sug->period_id,
                'document_date'        => now()->toDateString(),
                'description'          => $sug->description,
                'source_document_type' => $sug->source_event_type,
                'source_document_id'   => $sug->source_document_id,
                'lines'                => $journalLines,
            ]);

            // Explicit: accept does NOT post
            $sug->status = SuggestedJournal::STATUS_ACCEPTED;
            $sug->journal_entry_id = $draft->journal_entry_id;
            $sug->decided_by = $actorId;
            $sug->decided_at = now();
            $sug->decision_note = $note;
            $sug->row_version = ((int) $sug->row_version) + 1;
            $sug->save();

            $this->log('ACCEPT', 'SUGGESTED_JOURNAL', (string) $sug->suggested_journal_id, $actorId, [
                'journal_entry_id' => $draft->journal_entry_id,
                'note'             => $note,
            ]);

            return $sug->fresh(['lines']);
        });
    }

    public function reject(string $suggestedJournalId, ?string $actorId = null, ?string $note = null): SuggestedJournal
    {
        $sug = SuggestedJournal::where('suggested_journal_id', $suggestedJournalId)->firstOrFail();

        if (! $sug->isPending()) {
            throw new DomainException('این پیشنهاد قبلاً تصمیم‌گیری شده است.', 'fin.suggest.not_pending');
        }

        $sug->status = SuggestedJournal::STATUS_REJECTED;
        $sug->decided_by = $actorId;
        $sug->decided_at = now();
        $sug->decision_note = $note;
        $sug->row_version = ((int) $sug->row_version) + 1;
        $sug->save();

        $this->log('REJECT', 'SUGGESTED_JOURNAL', (string) $sug->suggested_journal_id, $actorId, [
            'note' => $note,
        ]);

        return $sug->fresh(['lines']);
    }

    public function listPending(?string $companyId = null): Collection
    {
        $q = SuggestedJournal::with('lines')
            ->where('status', SuggestedJournal::STATUS_PENDING)
            ->orderByDesc('created_at');

        if ($companyId) {
            $q->where('company_id', $companyId);
        }

        return $q->limit(100)->get();
    }

    /**
     * @return array{0: float, 1: float} debit, credit
     */
    protected function sideForRole(string $role, float $amount): array
    {
        // Debit-normal roles vs credit-normal
        $debitRoles = ['RECEIVABLE', 'EXPENSE', 'TAX_INPUT', 'CASH', 'ASSET'];
        $creditRoles = ['REVENUE', 'PAYABLE', 'TAX_OUTPUT', 'LIABILITY', 'EQUITY'];

        if (in_array($role, $debitRoles, true)) {
            return [$amount, 0.0];
        }
        if (in_array($role, $creditRoles, true)) {
            return [0.0, $amount];
        }

        // default credit
        return [0.0, $amount];
    }

    protected function log(
        string $action,
        string $subjectType,
        string $subjectId,
        ?string $actorId,
        array $payload
    ): void {
        SmartActionLog::create([
            'smart_action_log_id' => (string) Str::uuid(),
            'tenant_id'           => $this->requireTenantId(),
            'action_type'         => $action,
            'subject_type'        => $subjectType,
            'subject_id'          => $subjectId,
            'actor_id'            => $actorId,
            'payload_json'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at'          => now(),
        ]);
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
