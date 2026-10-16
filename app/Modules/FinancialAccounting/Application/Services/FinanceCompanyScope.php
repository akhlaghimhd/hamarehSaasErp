<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Base\Services\HoldingAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ADR-FIN-003 FIN-HOLD-W1 + W6 — Company Scope for FinancialAccounting.
 *
 * Hard split (FIN-HOLD-L03):
 * - Read  → assertCanReadCompany / isGroupWideRead / applyToQuery
 * - Write → assertCanOperateCompany / isGroupWideOperate
 *
 * FIN-HOLD-W6 / L15: denied operate attempts are written to event_outbox
 * (finance.access.operate_denied.v1) and application log.
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
            $this->recordOperateDenied($companyId, $allowed);

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

    /**
     * FIN-HOLD-W6 / L15 — mandatory audit of denied write attempts.
     *
     * @param  list<string>  $allowedCompanyIds
     */
    protected function recordOperateDenied(string $companyId, array $allowedCompanyIds): void
    {
        $ctx = Context::get('security_context');
        $userId = is_array($ctx) ? (string) ($ctx['user_id'] ?? '') : '';
        $tenantId = TenantContext::getInstance()->getTenantId()
            ?? (is_array($ctx) ? ($ctx['tenant_id'] ?? null) : null);

        Log::warning('finance.access.operate_denied', [
            'tenant_id' => $tenantId,
            'user_id' => $userId !== '' ? $userId : null,
            'company_id' => $companyId,
            'allowed_company_ids' => $allowedCompanyIds,
            'error_code' => 'fin.company_scope.operate_denied',
        ]);

        if (! $tenantId) {
            return;
        }

        try {
            if (! Schema::hasTable('event_outbox')) {
                return;
            }

            $aggregateId = $userId !== '' ? $userId : $companyId;

            DB::table('event_outbox')->insert([
                'event_id' => (string) Str::uuid(),
                'tenant_id' => (string) $tenantId,
                'aggregate_type' => 'finance.company_scope',
                'aggregate_id' => $aggregateId,
                'event_type' => 'finance.access.operate_denied.v1',
                'payload' => json_encode([
                    'company_id' => $companyId,
                    'user_id' => $userId !== '' ? $userId : null,
                    'allowed_company_ids' => $allowedCompanyIds,
                    'error_code' => 'fin.company_scope.operate_denied',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'status' => 1,
                'retry_count' => 0,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('event_outbox write failed', [
                'event_type' => 'finance.access.operate_denied.v1',
                'error' => $e->getMessage(),
            ]);
        }
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
