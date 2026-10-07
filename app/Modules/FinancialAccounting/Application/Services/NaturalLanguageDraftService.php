<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;

/**
 * FIN-P6-03 K6 — NL command → journal DRAFT only (never post).
 * Pattern: "بدهکار X به Y مبلغ N" / "debit X credit Y amount N"
 */
class NaturalLanguageDraftService
{
    public function __construct(
        protected JournalEntryService $journals = new JournalEntryService(),
        protected AccountSuggestionService $suggest = new AccountSuggestionService(),
        protected SmartActionAuditService $audit = new SmartActionAuditService()
    ) {
    }

    /**
     * @param  array{
     *   company_id: string,
     *   ledger_id: string,
     *   period_id: string,
     *   command: string,
     *   document_date?: string,
     *   actor_id?: string|null
     * }  $data
     * @return array{journal_entry_id: string, status: string, parsed: array, explanation: string}
     */
    public function createDraftFromCommand(array $data): array
    {
        $command = trim((string) ($data['command'] ?? ''));
        if ($command === '') {
            throw new DomainException('دستور خالی است.', 'fin.nl.empty');
        }

        $parsed = $this->parse($command, (string) $data['company_id']);

        $entry = $this->journals->createDraft([
            'ledger_id'            => $data['ledger_id'],
            'company_id'           => $data['company_id'],
            'period_id'            => $data['period_id'],
            'document_date'        => $data['document_date'] ?? now()->toDateString(),
            'description'          => 'NL: '.$command,
            'source_document_type' => 'NL_ASSISTANT',
            'lines'                => [
                [
                    'account_id'    => $parsed['debit_account_id'],
                    'debit_amount'  => $parsed['amount'],
                    'credit_amount' => 0,
                    'description'   => $parsed['debit_label'],
                ],
                [
                    'account_id'    => $parsed['credit_account_id'],
                    'debit_amount'  => 0,
                    'credit_amount' => $parsed['amount'],
                    'description'   => $parsed['credit_label'],
                ],
            ],
        ]);

        $this->audit->log(
            'K6',
            'NL_DRAFT',
            'GENERATED',
            [
                'command'          => $command,
                'parsed'           => $parsed,
                'journal_entry_id' => $entry->journal_entry_id,
            ],
            $data['actor_id'] ?? null,
            'fin_acc_journal_entries',
            $entry->journal_entry_id
        );

        return [
            'journal_entry_id' => $entry->journal_entry_id,
            'status'           => JournalEntry::STATUS_DRAFT,
            'parsed'           => $parsed,
            'explanation'      => $parsed['explanation'],
        ];
    }

    /**
     * @return array{
     *   amount: float,
     *   debit_account_id: string,
     *   credit_account_id: string,
     *   debit_label: string,
     *   credit_label: string,
     *   explanation: string
     * }
     */
    protected function parse(string $command, string $companyId): array
    {
        // amount: first number with optional separators
        if (! preg_match('/(\d+(?:[.,]\d+)?)/u', $command, $mAmt)) {
            throw new DomainException('مبلغ در دستور یافت نشد.', 'fin.nl.amount_missing');
        }
        $amount = (float) str_replace(',', '', $mAmt[1]);
        if ($amount <= 0) {
            throw new DomainException('مبلغ باید مثبت باشد.', 'fin.nl.bad_amount');
        }

        // Try explicit account codes like 1101 / 5101
        preg_match_all('/\b(\d{3,8})\b/', $command, $codes);
        $codeList = $codes[1] ?? [];

        $debitId = null;
        $creditId = null;
        $debitLabel = 'بدهکار';
        $creditLabel = 'بستانکار';

        if (count($codeList) >= 2) {
            $debitAcc = Account::query()->where('account_code', $codeList[0])->where('is_postable', true)->first();
            $creditAcc = Account::query()->where('account_code', $codeList[1])->where('is_postable', true)->first();
            if ($debitAcc && $creditAcc) {
                $debitId = (string) $debitAcc->account_id;
                $creditId = (string) $creditAcc->account_id;
                $debitLabel = $debitAcc->account_code.' '.$debitAcc->name;
                $creditLabel = $creditAcc->account_code.' '.$creditAcc->name;
            }
        }

        if (! $debitId || ! $creditId) {
            // history-based suggestions for both sides
            $s1 = $this->suggest->suggest($companyId, $command, 1);
            $s2 = $this->suggest->suggest($companyId, $command, 2);
            $d = $s1['suggestions'][0] ?? null;
            $c = $s2['suggestions'][0] ?? ($s1['suggestions'][1] ?? null);
            if (! $d || ! $c) {
                throw new DomainException(
                    'نتوانستیم حساب‌ها را از دستور یا تاریخچه تشخیص دهیم. کد حساب را صریح بنویسید.',
                    'fin.nl.accounts_unresolved'
                );
            }
            // ensure distinct
            if ($d['account_id'] === $c['account_id'] && isset($s1['suggestions'][1])) {
                $c = $s1['suggestions'][1];
            }
            $debitId = $d['account_id'];
            $creditId = $c['account_id'];
            $debitLabel = $d['account_code'].' '.$d['name'];
            $creditLabel = $c['account_code'].' '.$c['name'];
        }

        if ($debitId === $creditId) {
            throw new DomainException('حساب بدهکار و بستانکار یکسان شد.', 'fin.nl.same_account');
        }

        return [
            'amount'             => $amount,
            'debit_account_id'   => $debitId,
            'credit_account_id'  => $creditId,
            'debit_label'        => $debitLabel,
            'credit_label'       => $creditLabel,
            'explanation'        => "پیش‌نویس: بدهکار {$debitLabel} / بستانکار {$creditLabel} مبلغ {$amount} (ثبت قطعی نشده)",
        ];
    }
}
