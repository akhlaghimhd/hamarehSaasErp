<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Base\Services\HoldingAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

/**
 * ADR-FIN-003 FIN-HOLD-W1 — Company Scope for FinancialAccounting.
 *
 * Hard split (FIN-HOLD-L03):
 * - Read  → assertCanReadCompany / isGroupWideRead / applyToQuery
 * - Write → assertCanOperateCompany / isGroupWideOperate
 *
 * Rules:
 * - is_owner is NOT group-wide finance operator (FIN-HOLD-L06).
 * - Group observer may read group-wide; never operate.
 * - Soft SME (≤1 company, no COMPANY scopes) keeps soft read+operate on primary.
 * - No security context (CLI / bare unit tests): group-wide read+operate so internal tests stay practical.
 */
class FinanceCompanyScope
{
    public function __construct(
        protected ?HoldingAccessService $holding = null
    ) {
        $this->holding = $holding ?? new HoldingAccessService();
    }

    public function isGroupWideRead(): bool
    {
        if ($this->isUnauthenticatedInternalContext()) {
            return true;
        }

        return $this->holding->isGroupWideActor();
    }

    public function isGroupWideOperate(): bool
    {
        if ($this->isUnauthenticatedInternalContext()) {
            return true;
        }

        return $this->holding->isGroupWideOperator();
    }

    /**
     * @deprecated Use isGroupWideRead(). Means READ only.
     */
    public function isGroupWide(): bool
    {
        return $this->isGroupWideRead();
    }

    /**
     * @return list<string>
     */
    public function allowedCompanyIdsForRead(): array
    {
        if ($this->isGroupWideRead()) {
            return [];
        }

        $scoped = $this->holding->actorCompanyIds();
        if ($scoped !== []) {
            return $scoped;
        }

        $primary = $this->resolvePrimaryCompanyId();

        return $primary !== null ? [$primary] : [];
    }

    /**
     * @return list<string>
     */
    public function allowedCompanyIdsForOperate(): array
    {
        if ($this->isGroupWideOperate()) {
            return [];
        }

        $scoped = $this->holding->actorCompanyIds();
        if ($scoped !== []) {
            return $scoped;
        }

        if (! $this->isUnauthenticatedInternalContext()) {
            return [];
        }

        $primary = $this->resolvePrimaryCompanyId();

        return $primary !== null ? [$primary] : [];
    }

    /**
     * @return list<string>
     */
    public function allowedCompanyIds(): array
    {
        return $this->allowedCompanyIdsForRead();
    }

    public function assertCanReadCompany(string $companyId): void
    {
        if ($companyId === '') {
            throw new DomainException(
                'شناسه شرکت الزامی است.',
                'fin.company_scope.company_required'
            );
        }

        if ($this->isGroupWideRead()) {
            return;
        }

        $allowed = $this->allowedCompanyIdsForRead();
        if ($allowed === [] || ! in_array($companyId, $allowed, true)) {
            throw new DomainException(
                'مشاهده اسناد این شرکت برای شما مجاز نیست.',
                'fin.company_scope.read_denied'
            );
        }
    }

    public function assertCanOperateCompany(string $companyId): void
    {
        if ($companyId === '') {
            throw new DomainException(
                'شناسه شرکت الزامی است.',
                'fin.company_scope.company_required'
            );
        }

        if ($this->isGroupWideOperate()) {
            return;
        }

        $allowed = $this->allowedCompanyIdsForOperate();
        if ($allowed === [] || ! in_array($companyId, $allowed, true)) {
            throw new DomainException(
                'ثبت یا ویرایش اسناد این شرکت برای شما مجاز نیست.',
                'fin.company_scope.operate_denied'
            );
        }
    }

    /**
     * @deprecated Defaults to **read** (FIN-HOLD-L03).
     */
    public function assertCanAccessCompany(string $companyId): void
    {
        $this->assertCanReadCompany($companyId);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public function applyToQuery(Builder $query, ?string $companyIdFilter = null): Builder
    {
        if ($companyIdFilter !== null && $companyIdFilter !== '') {
            $this->assertCanReadCompany($companyIdFilter);
            $query->where('company_id', $companyIdFilter);

            return $query;
        }

        if ($this->isGroupWideRead()) {
            return $query;
        }

        $ids = $this->allowedCompanyIdsForRead();
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return $query;
        }

        $query->whereIn('company_id', $ids);

        return $query;
    }

    protected function isUnauthenticatedInternalContext(): bool
    {
        $ctx = Context::get('security_context');

        return ! is_array($ctx) || $ctx === [];
    }

    protected function resolvePrimaryCompanyId(): ?string
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (! $tenantId) {
            $ctx = Context::get('security_context');
            $tenantId = is_array($ctx) ? ($ctx['tenant_id'] ?? null) : null;
        }
        if (! $tenantId) {
            return null;
        }

        $id = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->where('is_primary', true)
            ->whereNull('deleted_at')
            ->value('company_id');

        if ($id) {
            return (string) $id;
        }

        $fallback = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->value('company_id');

        return $fallback ? (string) $fallback : null;
    }
}
