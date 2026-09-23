<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\CompanyFiscalAssignment;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ORG-P2-02 — Company ↔ fiscal period contract at Organization boundary.
 *
 * Does NOT import Accounting models. Validates period_id via DB against
 * fin_fiscal_periods for the same tenant (logical ref, Law 2.2).
 */
class CompanyFiscalAssignmentService
{
    public function assign(
        string $companyId,
        string $periodId,
        bool $isPrimary = false,
    ): CompanyFiscalAssignment {
        $tenantId = TenantContext::getInstance()->getTenantId();

        Company::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $this->assertPeriodBelongsToTenant($tenantId, $periodId);

        $existing = CompanyFiscalAssignment::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('period_id', $periodId)
            ->first();

        if ($existing) {
            if ($isPrimary && !$existing->is_primary) {
                return $this->setPrimary($existing->assignment_id);
            }

            return $existing;
        }

        return DB::transaction(function () use ($tenantId, $companyId, $periodId, $isPrimary) {
            if ($isPrimary) {
                CompanyFiscalAssignment::where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            return CompanyFiscalAssignment::create([
                'assignment_id' => (string) Str::uuid(),
                'tenant_id'     => $tenantId,
                'company_id'    => $companyId,
                'period_id'     => $periodId,
                'is_primary'    => $isPrimary,
                'status'        => 1,
                'row_version'   => 1,
            ]);
        });
    }

    public function setPrimary(string $assignmentId): CompanyFiscalAssignment
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = CompanyFiscalAssignment::where('tenant_id', $tenantId)
            ->where('assignment_id', $assignmentId)
            ->firstOrFail();

        return DB::transaction(function () use ($tenantId, $row) {
            CompanyFiscalAssignment::where('tenant_id', $tenantId)
                ->where('company_id', $row->company_id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $row->update([
                'is_primary'  => true,
                'row_version' => ((int) ($row->row_version ?? 1)) + 1,
            ]);

            return $row->fresh();
        });
    }

    public function listForCompany(string $companyId)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        return CompanyFiscalAssignment::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();
    }

    public function softDelete(string $assignmentId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = CompanyFiscalAssignment::where('tenant_id', $tenantId)
            ->where('assignment_id', $assignmentId)
            ->firstOrFail();

        $row->delete();
    }

    private function assertPeriodBelongsToTenant(string $tenantId, string $periodId): void
    {
        if (!Schema::hasTable('fin_fiscal_periods')) {
            throw new \Exception('جدول دوره‌های مالی در دسترس نیست.');
        }

        $exists = DB::table('fin_fiscal_periods')
            ->where('tenant_id', $tenantId)
            ->where('period_id', $periodId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            throw new \Exception('دوره مالی در این سازمان یافت نشد.');
        }
    }
}
