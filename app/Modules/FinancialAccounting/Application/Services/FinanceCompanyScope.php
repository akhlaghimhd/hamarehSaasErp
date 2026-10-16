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
 * Inactive company law (product B, 2026-10-16):
 * - Login / membership remain independent of company is_active.
 * - Operate (create/update/post/delete docs) requires company ACTIVE.
 * - Read of history on inactive company remains allowed (assertCanReadCompany).
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
     * Active companies only for operate pickers (product B).
     *
     * @return list<string>
     */
    public function allowedCompanyIdsForOperate(): array
    {
        if ($this->isGroupWideOperate()) {
            return [];
        }

        $scoped = $this->holding->actorCompanyIds();
        if ($scoped !== []) {
            return $this->filterActiveCompanyIds($scoped);
        }

        if (! $this->isUnauthenticatedInternalContext()) {
            return [];
        }

        $primary = $this->resolvePrimaryCompanyId();
        if ($primary === null) {
            return [];
        }

        return $this->filterActiveCompanyIds([$primary]);
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

        // Product B: inactive company blocks operate for every authenticated actor.
        if (! $this->isUnauthenticatedInternalContext()) {
            $this->assertCompanyIsActiveForOperate($companyId);
        }

        if ($this->isGroupWideOperate()) {
            return;
        }

        $allowed = $this->allowedCompanyIdsForOperate();
        $scoped = $this->holding->actorCompanyIds();

        if ($scoped !== [] && ! in_array($companyId, $scoped, true)) {
            $this->recordOperateDenied($companyId, $allowed);

            throw new DomainException(
                'ثبت یا ویرایش اسناد این شرکت برای شما مجاز نیست.',
                'fin.company_scope.operate_denied'
            );
        }

        if ($scoped === [] && ($allowed === [] || ! in_array($companyId, $allowed, true))) {
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
     * Whether the company is active for operational writes.
     * Missing / soft-deleted row ⇒ inactive.
     */
    public function isCompanyActive(string $companyId): bool
    {
        if ($companyId === '') {
            return false;
        }

        $tenantId = TenantContext::getInstance()->getTenantId();
        if (! $tenantId) {
            $ctx = Context::get('security_context');
            $tenantId = is_array($ctx) ? ($ctx['tenant_id'] ?? null) : null;
        }

        $q = DB::table('erp_companies')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        $row = $q->first(['is_active', 'status']);
        if (! $row) {
            return false;
        }

        if (property_exists($row, 'is_active') || isset($row->is_active)) {
            $active = $row->is_active;
            if ($active === false || $active === 0 || $active === '0') {
                return false;
            }
        }

        if (property_exists($row, 'status') || isset($row->status)) {
            $status = $row->status;
            if ($status !== null && (int) $status !== 1) {
                return false;
            }
        }

        return true;
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

    protected function assertCompanyIsActiveForOperate(string $companyId): void
    {
        if ($this->isCompanyActive($companyId)) {
            return;
        }

        $this->recordOperateDenied($companyId, $this->allowedCompanyIdsForOperate());

        throw new DomainException(
            'شرکت انتخاب‌شده غیرفعال است؛ ثبت یا ویرایش عملیات برای این شرکت مجاز نیست.',
            'fin.company_scope.company_inactive'
        );
    }

    /**
     * @param  list<string>  $companyIds
     * @return list<string>
     */
    protected function filterActiveCompanyIds(array $companyIds): array
    {
        $companyIds = array_values(array_unique(array_filter($companyIds)));
        if ($companyIds === []) {
            return [];
        }

        $tenantId = TenantContext::getInstance()->getTenantId();
        if (! $tenantId) {
            $ctx = Context::get('security_context');
            $tenantId = is_array($ctx) ? ($ctx['tenant_id'] ?? null) : null;
        }

        $q = DB::table('erp_companies')
            ->whereIn('company_id', $companyIds)
            ->whereNull('deleted_at')
            ->where(function ($w) {
                $w->where('is_active', true)->orWhere('is_active', 1);
            })
            ->where(function ($w) {
                $w->where('status', 1)->orWhereNull('status');
            });

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return array_values(array_map('strval', $q->pluck('company_id')->all()));
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
