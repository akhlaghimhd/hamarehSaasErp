<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\IcAccountMap;
use App\Modules\FinancialAccounting\Infrastructure\Models\IcJournalPair;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\IntercompanyPartner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FIN-P5-02/03 — Paired IC journal drafts + elimination draft on ELIMINATION entity.
 * Never auto-posts.
 */
class IntercompanyJournalService
{
    public function __construct(
        protected JournalEntryService $journals = new JournalEntryService()
    ) {
    }

    /**
     * @param  array{
     *   from_company_id: string,
     *   to_company_id: string,
     *   from_ledger_id: string,
     *   to_ledger_id: string,
     *   period_id: string,
     *   amount: float|string,
     *   from_offset_account_id: string,
     *   to_offset_account_id: string,
     *   document_date?: string,
     *   description?: string,
     *   ic_partner_id?: string|null
     * }  $data
     * @return array{pair: IcJournalPair, from_journal_entry_id: string, to_journal_entry_id: string}
     */
    public function createPairedDrafts(array $data): array
    {
        $tenantId = $this->requireTenantId();
        $fromId = (string) $data['from_company_id'];
        $toId = (string) $data['to_company_id'];
        $amount = round((float) $data['amount'], 4);

        if ($fromId === $toId) {
            throw new DomainException('شرکت مبدأ و مقصد IC یکسان است.', 'fin.ic.same_company');
        }
        if ($amount <= 0) {
            throw new DomainException('مبلغ IC باید مثبت باشد.', 'fin.ic.invalid_amount');
        }

        $this->assertPartnerExists($fromId, $toId, $data['ic_partner_id'] ?? null);

        $map = IcAccountMap::query()
            ->where('from_company_id', $fromId)
            ->where('to_company_id', $toId)
            ->where('is_active', true)
            ->first();

        if (! $map) {
            throw new DomainException(
                'نقشه حساب بین شرکتی برای این زوج شرکت یافت نشد.',
                'fin.ic.map_missing'
            );
        }

        if (! Ledger::where('ledger_id', $data['from_ledger_id'])->exists()
            || ! Ledger::where('ledger_id', $data['to_ledger_id'])->exists()) {
            throw new DomainException('دفتر کل مبدأ یا مقصد نامعتبر است.', 'fin.ic.invalid_ledger');
        }

        $desc = $data['description'] ?? ('IC '.$fromId.' → '.$toId);
        $date = $data['document_date'] ?? now()->toDateString();

        return DB::transaction(function () use (
            $tenantId, $fromId, $toId, $amount, $map, $data, $desc, $date
        ) {
            // From company: Dr Due-from (to) / Cr offset (e.g. revenue or cash)
            $fromDraft = $this->journals->createDraft([
                'ledger_id'            => $data['from_ledger_id'],
                'company_id'           => $fromId,
                'period_id'            => $data['period_id'],
                'document_date'        => $date,
                'description'          => $desc.' (طرف مبدأ)',
                'source_document_type' => 'IC_PAIR',
                'lines'                => [
                    [
                        'account_id'    => $map->due_from_account_id,
                        'debit_amount'  => $amount,
                        'credit_amount' => 0,
                        'description'   => 'IC due-from',
                    ],
                    [
                        'account_id'    => $data['from_offset_account_id'],
                        'debit_amount'  => 0,
                        'credit_amount' => $amount,
                        'description'   => 'IC offset from',
                    ],
                ],
            ]);

            // To company: Dr offset / Cr Due-to (from)
            $toDraft = $this->journals->createDraft([
                'ledger_id'            => $data['to_ledger_id'],
                'company_id'           => $toId,
                'period_id'            => $data['period_id'],
                'document_date'        => $date,
                'description'          => $desc.' (طرف مقصد)',
                'source_document_type' => 'IC_PAIR',
                'lines'                => [
                    [
                        'account_id'    => $data['to_offset_account_id'],
                        'debit_amount'  => $amount,
                        'credit_amount' => 0,
                        'description'   => 'IC offset to',
                    ],
                    [
                        'account_id'    => $map->due_to_account_id,
                        'debit_amount'  => 0,
                        'credit_amount' => $amount,
                        'description'   => 'IC due-to',
                    ],
                ],
            ]);

            $pair = new IcJournalPair();
            $pair->forceFill([
                'ic_journal_pair_id'     => (string) Str::uuid(),
                'tenant_id'              => $tenantId,
                'from_company_id'        => $fromId,
                'to_company_id'          => $toId,
                'from_journal_entry_id'  => $fromDraft->journal_entry_id,
                'to_journal_entry_id'    => $toDraft->journal_entry_id,
                'period_id'              => $data['period_id'],
                'amount'                 => $amount,
                'description'            => $desc,
                'status'                 => IcJournalPair::STATUS_DRAFT_PAIR,
                'ic_partner_id'          => $data['ic_partner_id'] ?? $map->ic_partner_id,
                'row_version'            => 1,
            ]);
            $pair->save();

            return [
                'pair'                 => $pair->fresh(),
                'from_journal_entry_id'=> $fromDraft->journal_entry_id,
                'to_journal_entry_id'  => $toDraft->journal_entry_id,
            ];
        });
    }

    /**
     * Elimination draft on ELIMINATION company: reverse IC due-from/due-to balances (simple).
     *
     * @param  array{
     *   elimination_company_id: string,
     *   ledger_id: string,
     *   period_id: string,
     *   due_from_account_id: string,
     *   due_to_account_id: string,
     *   amount: float|string,
     *   document_date?: string,
     *   description?: string
     * }  $data
     */
    public function createEliminationDraft(array $data): array
    {
        $elimId = (string) $data['elimination_company_id'];
        $company = Company::query()->where('company_id', $elimId)->first();
        if (! $company || $company->entity_kind !== Company::ENTITY_KIND_ELIMINATION) {
            throw new DomainException(
                'شرکت حذف باید entity_kind=ELIMINATION باشد.',
                'fin.ic.not_elimination_entity'
            );
        }

        $amount = round((float) $data['amount'], 4);
        if ($amount <= 0) {
            throw new DomainException('مبلغ حذف باید مثبت باشد.', 'fin.ic.elim_amount');
        }

        $draft = $this->journals->createDraft([
            'ledger_id'            => $data['ledger_id'],
            'company_id'           => $elimId,
            'period_id'            => $data['period_id'],
            'document_date'        => $data['document_date'] ?? now()->toDateString(),
            'description'          => $data['description'] ?? 'حذف بین شرکتی (پیش‌نویس)',
            'source_document_type' => 'IC_ELIMINATION',
            'lines'                => [
                [
                    'account_id'    => $data['due_to_account_id'],
                    'debit_amount'  => $amount,
                    'credit_amount' => 0,
                    'description'   => 'Elim Dr due-to',
                ],
                [
                    'account_id'    => $data['due_from_account_id'],
                    'debit_amount'  => 0,
                    'credit_amount' => $amount,
                    'description'   => 'Elim Cr due-from',
                ],
            ],
        ]);

        return [
            'journal_entry_id' => $draft->journal_entry_id,
            'status'           => $draft->status,
        ];
    }

    public function upsertAccountMap(array $data): IcAccountMap
    {
        $tenantId = $this->requireTenantId();

        $existing = IcAccountMap::query()
            ->where('from_company_id', $data['from_company_id'])
            ->where('to_company_id', $data['to_company_id'])
            ->first();

        if ($existing) {
            $existing->due_from_account_id = $data['due_from_account_id'];
            $existing->due_to_account_id = $data['due_to_account_id'];
            $existing->ic_partner_id = $data['ic_partner_id'] ?? $existing->ic_partner_id;
            $existing->description = $data['description'] ?? $existing->description;
            $existing->is_active = $data['is_active'] ?? true;
            $existing->row_version = ((int) $existing->row_version) + 1;
            $existing->save();

            return $existing->fresh();
        }

        $row = new IcAccountMap();
        $row->forceFill([
            'ic_account_map_id'    => (string) Str::uuid(),
            'tenant_id'            => $tenantId,
            'from_company_id'      => $data['from_company_id'],
            'to_company_id'        => $data['to_company_id'],
            'due_from_account_id'  => $data['due_from_account_id'],
            'due_to_account_id'    => $data['due_to_account_id'],
            'ic_partner_id'        => $data['ic_partner_id'] ?? null,
            'is_active'            => $data['is_active'] ?? true,
            'description'          => $data['description'] ?? null,
            'row_version'          => 1,
        ]);
        $row->save();

        return $row->fresh();
    }

    protected function assertPartnerExists(string $fromId, string $toId, ?string $partnerId): void
    {
        $q = IntercompanyPartner::query()
            ->where('from_company_id', $fromId)
            ->where('to_company_id', $toId)
            ->where('is_active', true);

        if ($partnerId) {
            $q->where('ic_partner_id', $partnerId);
        }

        if (! $q->exists()) {
            // Soft path: allow if caller skips Org seed in unit tests without partners table rows
            // but still require at least one active partner in production data.
            throw new DomainException(
                'شریک بین شرکتی فعال در سازمان برای این زوج یافت نشد.',
                'fin.ic.partner_missing'
            );
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
