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
 * ADR-FIN-002 / FIN-CSN-F0-01 + D7/A01 — Company Scope for FinancialAccounting.
 *
 * - Tenant owner (is_owner): group-wide (all companies).
 * - COMPANY scopes present: only those company_ids (child / parent multi).
 * - Authenticated actor with zero COMPANY scopes (SME): **primary company only** — not whole tenant.
 * - No security context (CLI / bare unit tests): group-wide so service tests stay practical.
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
        $ctx = Context::get('security_context');

        if (is_array($ctx) && ! empty($ctx['is_owner'])) {
            return true;
        }

        $scoped = $this->holding->actorCompanyIds();
        if ($scoped !== []) {
            return false;
        }

        // No actor bound → internal/tests without security_context
        if (! is_array($ctx) || $ctx === []) {
            return true;
        }

        // Authenticated non-owner, no COMPANY scopes → SME soft path (not group-wide)
        return false;
    }

    /**
     * @return list<string> Empty means group-wide (caller must not treat as "none").
     */
    public function allowedCompanyIds(): array
    {
        if ($this->isGroupWide()) {
            return [];
        }

        $scoped = $this->holding->actorCompanyIds();
        if ($scoped !== []) {
            return $scoped;
        }

        $primary = $this->resolvePrimaryCompanyId();

        return $primary !== null ? [$primary] : [];
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
