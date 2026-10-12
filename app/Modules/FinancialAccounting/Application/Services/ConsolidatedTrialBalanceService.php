<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalItem;
use App\Modules\Organization\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * FIN-P5-04 + ADR-FIN-002 — consolidated TB:
 * - assert access to consolidation root
 * - descendant operating companies ∩ allowedCompanyIds (unless group-wide)
 */
class ConsolidatedTrialBalanceService
{
    public function __construct(
        protected FinanceCompanyScope $companyScope = new FinanceCompanyScope()
    ) {
    }

    /**
     * @return list<array{account_id: string, account_code: string, name: string, account_type: int, debit: string, credit: string, balance: string}>
     */
    public function run(string $consolidationCompanyId, string $periodId): array
    {
        $this->companyScope->assertCanAccessCompany($consolidationCompanyId);

        $tenantId = TenantContext::getInstance()->getTenantId();

        $root = Company::withoutGlobalScopes()
            ->where('company_id', $consolidationCompanyId)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        if (! $root) {
            throw new DomainException('شرکت تلفیق یافت نشد.', 'fin.consol.company_missing');
        }

        $companyIds = $this->operatingDescendants($consolidationCompanyId, $tenantId ? (string) $tenantId : null);
        if ($companyIds === []) {
            if ($root->entity_kind === Company::ENTITY_KIND_OPERATING) {
                $companyIds = [$consolidationCompanyId];
            }
        }

        // ADR-FIN-002: never include companies outside actor Scope (except group-wide owner).
        if (! $this->companyScope->isGroupWide()) {
            $allowed = $this->companyScope->allowedCompanyIds();
            if ($allowed === []) {
                return [];
            }
            $companyIds = array_values(array_intersect($companyIds, $allowed));
            // Root may be consolidation-only and not in operating set; still require access already asserted.
            // If root itself is OPERATING and in tree, intersection already applied.
        }

        if ($companyIds === []) {
            return [];
        }

        $entryIds = JournalEntry::query()
            ->whereIn('company_id', $companyIds)
            ->where('period_id', $periodId)
            ->where('status', JournalEntry::STATUS_POSTED)
            ->pluck('journal_entry_id');

        if ($entryIds->isEmpty()) {
            return [];
        }

        $rows = JournalItem::query()
            ->select([
                'account_id',
                DB::raw('SUM(debit_amount) as total_debit'),
                DB::raw('SUM(credit_amount) as total_credit'),
            ])
            ->whereIn('journal_entry_id', $entryIds)
            ->groupBy('account_id')
            ->get();

        $accounts = Account::query()
            ->whereIn('account_id', $rows->pluck('account_id'))
            ->get()
            ->keyBy('account_id');

        return $rows->map(function ($row) use ($accounts) {
            $acc = $accounts->get($row->account_id);
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            return [
                'account_id'   => $row->account_id,
                'account_code' => $acc?->account_code ?? '',
                'name'         => $acc?->name ?? '',
                'account_type' => $acc?->account_type ?? 0,
                'debit'        => number_format($debit, 4, '.', ''),
                'credit'       => number_format($credit, 4, '.', ''),
                'balance'      => number_format($debit - $credit, 4, '.', ''),
            ];
        })->sortBy('account_code')->values()->all();
    }

    /**
     * @return list<string>
     */
    protected function operatingDescendants(string $rootId, ?string $tenantId): array
    {
        $ids = [];
        $queue = [$rootId];
        $seen = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            $children = Company::withoutGlobalScopes()
                ->where('parent_company_id', $current)
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->get(['company_id', 'entity_kind']);

            foreach ($children as $child) {
                if ($child->entity_kind === Company::ENTITY_KIND_OPERATING) {
                    $ids[] = (string) $child->company_id;
                }
                if (in_array($child->entity_kind, [
                    Company::ENTITY_KIND_OPERATING,
                    Company::ENTITY_KIND_CONSOLIDATION,
                ], true)) {
                    $queue[] = (string) $child->company_id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
