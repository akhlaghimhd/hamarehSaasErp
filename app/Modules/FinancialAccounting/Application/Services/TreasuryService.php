<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\CashAccount;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\FinancialAccounting\Infrastructure\Models\TreasuryDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FIN-P1-06 — Receipt/payment documents; optional GL draft+post via JournalEntryService.
 */
class TreasuryService
{
    public function __construct(
        protected JournalEntryService $journals = new JournalEntryService(),
        protected OpenItemService $openItems = new OpenItemService()
    ) {
    }

    public function createCashAccount(array $data): CashAccount
    {
        $tenantId = $this->requireTenantId();
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            throw new DomainException('کد حساب نقدی الزامی است.', 'fin.cash.code_required');
        }

        return CashAccount::create([
            'cash_account_id' => (string) Str::uuid(),
            'tenant_id'       => $tenantId,
            'company_id'      => $data['company_id'],
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'gl_account_id'   => $data['gl_account_id'],
            'code'            => $code,
            'name'            => (string) ($data['name'] ?? $code),
            'cash_kind'       => $data['cash_kind'] ?? 'BANK',
            'is_active'       => true,
            'status'          => 1,
            'row_version'     => 1,
        ]);
    }

    /**
     * @param  array{
     *   company_id: string,
     *   period_id: string,
     *   cash_account_id: string,
     *   document_type: string,
     *   document_date: string,
     *   amount: float|string,
     *   counterparty_name?: string|null,
     *   counterparty_open_item_id?: string|null,
     *   description?: string|null,
     *   offset_account_id?: string|null,
     *   ledger_id?: string|null,
     *   auto_post?: bool
     * }  $data
     */
    public function createDocument(array $data): TreasuryDocument
    {
        $tenantId = $this->requireTenantId();
        $type = (string) ($data['document_type'] ?? '');
        if (! in_array($type, [TreasuryDocument::TYPE_RECEIPT, TreasuryDocument::TYPE_PAYMENT], true)) {
            throw new DomainException('نوع سند خزانه نامعتبر است.', 'fin.treasury.invalid_type');
        }

        $amount = round((float) ($data['amount'] ?? 0), 4);
        if ($amount <= 0) {
            throw new DomainException('مبلغ باید بزرگ‌تر از صفر باشد.', 'fin.treasury.amount');
        }

        $cash = CashAccount::where('cash_account_id', $data['cash_account_id'])->first();
        if (! $cash) {
            throw new DomainException('حساب نقدی یافت نشد.', 'fin.treasury.cash_missing');
        }

        return DB::transaction(function () use ($tenantId, $data, $type, $amount, $cash) {
            $doc = TreasuryDocument::create([
                'treasury_document_id'      => (string) Str::uuid(),
                'tenant_id'                 => $tenantId,
                'company_id'                => $data['company_id'],
                'period_id'                 => $data['period_id'],
                'cash_account_id'           => $cash->cash_account_id,
                'document_type'             => $type,
                'document_date'             => $data['document_date'],
                'status'                    => TreasuryDocument::STATUS_DRAFT,
                'amount'                    => $amount,
                'currency_id'               => $data['currency_id'] ?? null,
                'counterparty_name'         => $data['counterparty_name'] ?? null,
                'counterparty_open_item_id' => $data['counterparty_open_item_id'] ?? null,
                'description'               => $data['description'] ?? null,
                'row_version'               => 1,
            ]);

            if (! empty($data['auto_post'])) {
                $doc = $this->postToGl($doc->treasury_document_id, [
                    'ledger_id'         => $data['ledger_id'] ?? null,
                    'offset_account_id' => $data['offset_account_id'],
                ]);
            }

            return $doc->fresh();
        });
    }

    /**
     * @param  array{ledger_id?: string|null, offset_account_id: string}  $opts
     */
    public function postToGl(string $treasuryDocumentId, array $opts): TreasuryDocument
    {
        $doc = TreasuryDocument::where('treasury_document_id', $treasuryDocumentId)->firstOrFail();

        if (! $doc->isDraft()) {
            throw new DomainException('فقط پیش‌نویس خزانه قابل ثبت در دفتر است.', 'fin.treasury.not_draft');
        }

        $cash = CashAccount::where('cash_account_id', $doc->cash_account_id)->firstOrFail();
        $amount = (float) $doc->amount;
        $cashGl = (string) $cash->gl_account_id;
        $offset = (string) $opts['offset_account_id'];

        $ledgerId = (string) ($opts['ledger_id'] ?? '');
        if ($ledgerId === '') {
            $ledger = Ledger::query()
                ->where('company_id', $doc->company_id)
                ->where('is_leading', true)
                ->first()
                ?? Ledger::query()->where('company_id', $doc->company_id)->first();
            if (! $ledger) {
                throw new DomainException('دفتر کل پیش‌فرض برای شرکت یافت نشد.', 'fin.treasury.no_ledger');
            }
            $opts['ledger_id'] = (string) $ledger->ledger_id;
        }

        if ($doc->document_type === TreasuryDocument::TYPE_RECEIPT) {
            $lines = [
                ['account_id' => $cashGl, 'debit_amount' => $amount],
                ['account_id' => $offset, 'credit_amount' => $amount],
            ];
        } else {
            $lines = [
                ['account_id' => $offset, 'debit_amount' => $amount],
                ['account_id' => $cashGl, 'credit_amount' => $amount],
            ];
        }

        return DB::transaction(function () use ($doc, $opts, $lines) {
            $draft = $this->journals->createDraft([
                'ledger_id'            => $opts['ledger_id'],
                'company_id'           => $doc->company_id,
                'period_id'            => $doc->period_id,
                'document_date'        => $doc->document_date->toDateString(),
                'description'          => $doc->description ?? ('خزانه '.$doc->document_type),
                'source_document_type' => 'TREASURY',
                'source_document_id'   => $doc->treasury_document_id,
                'lines'                => $lines,
            ]);

            $posted = $this->journals->post($draft->journal_entry_id);

            if ($doc->counterparty_open_item_id) {
                $this->openItems->allocate(
                    (string) $doc->counterparty_open_item_id,
                    (float) $doc->amount,
                    [
                        'treasury_document_id' => $doc->treasury_document_id,
                        'journal_entry_id'     => $posted->journal_entry_id,
                        'allocation_date'      => $doc->document_date->toDateString(),
                    ]
                );
            }

            $doc->status = TreasuryDocument::STATUS_POSTED;
            $doc->journal_entry_id = $posted->journal_entry_id;
            $doc->row_version = ((int) ($doc->row_version ?? 1)) + 1;
            $doc->save();

            return $doc->fresh();
        });
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
