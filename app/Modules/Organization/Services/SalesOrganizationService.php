<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\SalesOrganization;
use App\Modules\Organization\Models\SalesOrgAssignment;
use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use Illuminate\Support\Str;

class SalesOrganizationService
{
    public function listForTenant(bool $onlyTrashed = false)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = SalesOrganization::where('tenant_id', $tenantId)
            ->with(['assignments', 'company'])
            ->withCount(['assignments as assignment_count' => function ($q) {
                $q->whereNull('deleted_at');
            }])
            ->orderBy('code');

        if ($onlyTrashed) {
            $query->onlyTrashed();
        }

        return $query->get();
    }

    public function find(string $salesOrgId, bool $withTrashed = false): SalesOrganization
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = SalesOrganization::where('tenant_id', $tenantId)
            ->where('sales_org_id', $salesOrgId)
            ->with(['assignments', 'company', 'salesAreas']);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
    }

    public function create(
        string $code,
        string $name,
        ?string $companyId = null,
        ?string $description = null,
        bool $isActive = true
    ): SalesOrganization {
        $tenantId = TenantContext::getInstance()->getTenantId();

        OrgSalesPurchPackGuard::assertSalesStructure();

        if (SalesOrganization::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new DomainException('کد سازمان فروش تکراری است.', 'duplicate_sales_org_code');
        }

        if ($companyId) {
            Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();
        }

        return SalesOrganization::create([
            'sales_org_id' => (string) Str::uuid(),
            'tenant_id'    => $tenantId,
            'code'         => $code,
            'name'         => $name,
            'description'  => $description,
            'company_id'   => $companyId,
            'is_active'    => $isActive,
            'row_version'  => 1,
        ]);
    }

    public function update(
        string $salesOrgId,
        string $code,
        string $name,
        ?string $companyId = null,
        ?string $description = null,
        ?bool $isActive = null
    ): SalesOrganization {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = SalesOrganization::where('tenant_id', $tenantId)
            ->where('sales_org_id', $salesOrgId)
            ->firstOrFail();

        if ($row->code !== $code
            && SalesOrganization::where('tenant_id', $tenantId)->where('code', $code)->where('sales_org_id', '!=', $salesOrgId)->exists()) {
            throw new DomainException('کد سازمان فروش تکراری است.', 'duplicate_sales_org_code');
        }

        if ($companyId) {
            Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();
        }

        $payload = [
            'code'        => $code,
            'name'        => $name,
            'description' => $description,
            'company_id'  => $companyId,
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ];
        if ($isActive !== null) {
            $payload['is_active'] = $isActive;
        }
        $row->update($payload);

        return $row->fresh(['assignments', 'company']) ?? $row;
    }

    public function softDelete(string $salesOrgId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = SalesOrganization::where('tenant_id', $tenantId)
            ->where('sales_org_id', $salesOrgId)
            ->firstOrFail();

        $activeAssignments = SalesOrgAssignment::where('tenant_id', $tenantId)
            ->where('sales_org_id', $salesOrgId)
            ->whereNull('deleted_at')
            ->count();

        if ($activeAssignments > 0) {
            throw new DomainException(
                'این سازمان فروش دارای تخصیص فعال است و قابل حذف نیست.',
                'sales_org_has_assignments'
            );
        }

        $row->delete();
    }

    public function restore(string $salesOrgId): SalesOrganization
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = SalesOrganization::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('sales_org_id', $salesOrgId)
            ->firstOrFail();

        if (SalesOrganization::where('tenant_id', $tenantId)->where('code', $row->code)->exists()) {
            throw new DomainException(
                'کد این سازمان فروش با یک رکورد فعال دیگر تداخل دارد.',
                'restore_code_conflict'
            );
        }

        $row->restore();
        $row->update([
            'is_active'   => false,
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ]);

        return $row->fresh(['assignments', 'company']) ?? $row;
    }

    public function listAssignments(string $salesOrgId)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        SalesOrganization::where('tenant_id', $tenantId)->where('sales_org_id', $salesOrgId)->firstOrFail();

        return SalesOrgAssignment::where('tenant_id', $tenantId)
            ->where('sales_org_id', $salesOrgId)
            ->orderBy('created_at')
            ->get();
    }

    public function assign(string $salesOrgId, ?string $companyId = null, ?string $branchId = null): SalesOrgAssignment
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        SalesOrganization::where('tenant_id', $tenantId)->where('sales_org_id', $salesOrgId)->firstOrFail();

        if ($companyId) {
            Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();
        }
        if ($branchId) {
            Branch::where('tenant_id', $tenantId)->where('branch_id', $branchId)->firstOrFail();
        }
        if (!$companyId && !$branchId) {
            throw new DomainException(
                'حداقل یکی از company_id یا branch_id الزامی است.',
                'assignment_target_required'
            );
        }

        $existing = SalesOrgAssignment::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('sales_org_id', $salesOrgId)
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->first();

        if ($existing && !$existing->trashed()) {
            $existing->update([
                'is_active'   => true,
                'row_version' => ((int) ($existing->row_version ?? 1)) + 1,
            ]);

            return $existing->fresh() ?? $existing;
        }

        if ($existing && $existing->trashed()) {
            $existing->restore();
            $existing->update([
                'is_active'   => true,
                'row_version' => ((int) ($existing->row_version ?? 1)) + 1,
            ]);

            return $existing->fresh() ?? $existing;
        }

        return SalesOrgAssignment::create([
            'assignment_id' => (string) Str::uuid(),
            'tenant_id'     => $tenantId,
            'sales_org_id'  => $salesOrgId,
            'company_id'    => $companyId,
            'branch_id'     => $branchId,
            'is_active'     => true,
            'row_version'   => 1,
        ]);
    }

    public function unassign(string $assignmentId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = SalesOrgAssignment::where('tenant_id', $tenantId)
            ->where('assignment_id', $assignmentId)
            ->firstOrFail();

        $row->delete();
    }
}
