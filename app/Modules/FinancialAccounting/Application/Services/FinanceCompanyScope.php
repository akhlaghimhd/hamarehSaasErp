<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Exceptions\DomainException;
use App\Base\Services\HoldingAccessService;
use Illuminate\Database\Eloquent\Builder;

/**
 * ADR-FIN-002 / FIN-CSN-F0-01 — Company Scope for all FinancialAccounting services.
 *
 * Permission ∧ Scope: never grant cross-company access on permission alone.
 * Soft path (no COMPANY scopes / tenant owner): group-wide (SME unchanged).
 * Child with COMPANY scopes: only those company_ids.
 */
class FinanceCompanyScope
{
    public function __construct(
        protected ?HoldingAccessService $holding = null
    ) {
        $this->holding = $holding ?? new HoldingAccessService();
    }

    public function isGroupWide(): bool
    {
        return $this->holding->isGroupWideActor();
    }

    /**
     * Company reference ids the actor may access. Empty when group-wide (caller must not treat as "none").
     *
     * @return list<string>
     */
    public function allowedCompanyIds(): array
    {
        if ($this->isGroupWide()) {
            return [];
        }

        return $this->holding->actorCompanyIds();
    }

    public function assertCanAccessCompany(string $companyId): void
    {
        if ($companyId === '') {
            throw new DomainException(
                'شناسه شرکت الزامی است.',
                'fin.company_scope.company_required'
            );
        }

        if ($this->isGroupWide()) {
            return;
        }

        $allowed = $this->allowedCompanyIds();
        if ($allowed === [] || ! in_array($companyId, $allowed, true)) {
            throw new DomainException(
                'دسترسی به اسناد این شرکت برای شما مجاز نیست.',
                'fin.company_scope.denied'
            );
        }
    }

    /**
     * Constrain a query that has company_id column.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public function applyToQuery(Builder $query, ?string $companyIdFilter = null): Builder
    {
        if ($companyIdFilter !== null && $companyIdFilter !== '') {
            $this->assertCanAccessCompany($companyIdFilter);
            $query->where('company_id', $companyIdFilter);

            return $query;
        }

        if ($this->isGroupWide()) {
            return $query;
        }

        $ids = $this->allowedCompanyIds();
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return $query;
        }

        $query->whereIn('company_id', $ids);

        return $query;
    }
}
