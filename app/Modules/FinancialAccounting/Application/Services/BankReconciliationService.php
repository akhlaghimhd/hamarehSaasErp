<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\BankStatement;
use App\Modules\FinancialAccounting\Infrastructure\Models\BankStatementLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** FIN-P1-10 — Bank statement + match lines. */
class BankReconciliationService
{
    public function createStatement(array $data): BankStatement
    {
        $tenantId = $this->requireTenantId();

        return DB::transaction(function () use ($tenantId, $data) {
            $stmt = BankStatement::create([
                'bank_statement_id' => (string) Str::uuid(),
                'tenant_id'         => $tenantId,
                'company_id'        => $data['company_id'],
                'cash_account_id'   => $data['cash_account_id'],
                'statement_date'    => $data['statement_date'],
                'reference'         => $data['reference'] ?? null,
                'opening_balance'   => $data['opening_balance'] ?? 0,
                'closing_balance'   => $data['closing_balance'] ?? 0,
                'status'            => BankStatement::STATUS_OPEN,
                'row_version'       => 1,
            ]);

            $sort = 0;
            foreach ($data['lines'] ?? [] as $line) {
                BankStatementLine::create([
                    'bank_statement_line_id' => (string) Str::uuid(),
                    'bank_statement_id'      => $stmt->bank_statement_id,
                    'tenant_id'              => $tenantId,
                    'line_date'              => $line['line_date'],
                    'description'            => $line['description'] ?? null,
                    'debit_amount'           => $line['debit_amount'] ?? 0,
                    'credit_amount'          => $line['credit_amount'] ?? 0,
                    'status'                 => BankStatementLine::STATUS_OPEN,
                    'sort_order'             => $sort++,
                    'created_at'             => now(),
                ]);
            }

            return $stmt->fresh(['lines']);
        });
    }

    public function matchLine(
        string $lineId,
        ?string $treasuryDocumentId = null,
        ?string $journalEntryId = null
    ): BankStatementLine {
        $line = BankStatementLine::where('bank_statement_line_id', $lineId)->firstOrFail();

        if ($line->status === BankStatementLine::STATUS_MATCHED) {
            throw new DomainException('سطر قبلاً تطبیق شده است.', 'fin.recon.already_matched');
        }

        if (! $treasuryDocumentId && ! $journalEntryId) {
            throw new DomainException('حداقل یکی از سند خزانه یا سند دفتر لازم است.', 'fin.recon.match_target');
        }

        $line->status = BankStatementLine::STATUS_MATCHED;
        $line->matched_treasury_document_id = $treasuryDocumentId;
        $line->matched_journal_entry_id = $journalEntryId;
        $line->save();

        $this->maybeCloseStatement((string) $line->bank_statement_id);

        return $line->fresh();
    }

    protected function maybeCloseStatement(string $statementId): void
    {
        $open = BankStatementLine::where('bank_statement_id', $statementId)
            ->where('status', BankStatementLine::STATUS_OPEN)
            ->exists();

        if (! $open) {
            BankStatement::where('bank_statement_id', $statementId)->update([
                'status'      => BankStatement::STATUS_RECONCILED,
                'row_version' => DB::raw('row_version + 1'),
            ]);
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
