<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\CompanyNumberingSetting;
use Illuminate\Support\Str;

/**
 * FIN-HOLD-W3 / ADR-FIN-003 L07 — optional branch series from fiscal year start.
 */
class CompanyNumberingService
{
    public function __construct(
        protected FinanceCompanyScope $companyScope = new FinanceCompanyScope()
    ) {
    }

    public function getForCompany(string $companyId): CompanyNumberingSetting
    {
        $this->companyScope->assertCanReadCompany($companyId);
        $tenantId = $this->requireTenantId();

        $row = CompanyNumberingSetting::query()
            ->where('company_id', $companyId)
            ->first();

        if ($row) {
            return $row;
        }

        return CompanyNumberingSetting::make([
            'setting_id'                     => (string) Str::uuid(),
            'tenant_id'                      => $tenantId,
            'company_id'                     => $companyId,
            'branch_series_enabled'          => false,
            'branch_series_from_fiscal_year' => null,
            'row_version'                    => 1,
        ]);
    }

    /**
     * Enable (or update effective FY) for branch-separated numbering.
     * L07: change only effective from a fiscal year key (never mid-series without FY boundary).
     *
     * @param  string  $fromFiscalYearKey  e.g. 1404 — series uses branch only for documents in this FY and later
     */
    public function enableBranchSeries(string $companyId, string $fromFiscalYearKey, ?string $actorId = null): CompanyNumberingSetting
    {
        $this->companyScope->assertCanOperateCompany($companyId);
        $tenantId = $this->requireTenantId();

        $fy = trim($fromFiscalYearKey);
        if ($fy === '' || ! preg_match('/^\d{4}$/', $fy)) {
            throw new DomainException(
                'سال مالی برای شروع سری شعبه باید چهار رقمی باشد.',
                'fin.numbering.invalid_fy'
            );
        }

        $row = CompanyNumberingSetting::query()->where('company_id', $companyId)->first();
        if (! $row) {
            $row = new CompanyNumberingSetting();
            $row->forceFill([
                'setting_id'  => (string) Str::uuid(),
                'tenant_id'   => $tenantId,
                'company_id'  => $companyId,
                'row_version' => 1,
            ]);
        } else {
            $row->row_version = ((int) ($row->row_version ?? 1)) + 1;
        }

        $row->branch_series_enabled = true;
        $row->branch_series_from_fiscal_year = $fy;
        $row->updated_by = $actorId;
        $row->updated_at = now();
        $row->save();

        return $row->fresh() ?? $row;
    }

    public function disableBranchSeries(string $companyId, ?string $actorId = null): CompanyNumberingSetting
    {
        $this->companyScope->assertCanOperateCompany($companyId);

        $row = CompanyNumberingSetting::query()->where('company_id', $companyId)->first();
        if (! $row) {
            return $this->getForCompany($companyId);
        }

        $row->branch_series_enabled = false;
        $row->updated_by = $actorId;
        $row->updated_at = now();
        $row->row_version = ((int) ($row->row_version ?? 1)) + 1;
        $row->save();

        return $row->fresh() ?? $row;
    }

    /**
     * Whether sequence allocation should include branch_id for this document FY.
     */
    public function shouldUseBranchSeries(string $companyId, string $fiscalYearKey, ?string $branchId): bool
    {
        if ($branchId === null || $branchId === '') {
            return false;
        }

        $row = CompanyNumberingSetting::query()->where('company_id', $companyId)->first();
        if (! $row || ! $row->branch_series_enabled) {
            return false;
        }

        $from = (string) ($row->branch_series_from_fiscal_year ?? '');
        if ($from === '') {
            return false;
        }

        return $fiscalYearKey >= $from;
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
