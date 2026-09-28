<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\PurchasingOrganization;
use App\Modules\Organization\Models\PurchOrgAssignment;
use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use Illuminate\Support\Str;

class PurchasingOrganizationService
{
    public function listForTenant(bool $onlyTrashed = false)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = PurchasingOrganization::where('tenant_id', $tenantId)
            ->with(['assignments', 'company'])
            ->orderBy('code');

        if ($onlyTrashed) {
            $query->onlyTrashed();
        }

        return $query->get();
    }

    public function find(string $purchOrgId, bool $withTrashed = false): PurchasingOrganization
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = PurchasingOrganization::where('tenant_id', $tenantId)
            ->where('purch_org_id', $purchOrgId)
            ->with(['assignments', 'company']);

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
        bool $isActive = true,
        bool $isReference = false
    ): PurchasingOrganization {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (PurchasingOrganization::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new DomainException('کد سازمان خرید تکراری است.', 'duplicate_purch_org_code');
        }

        if ($companyId) {
            Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();
        }

        return PurchasingOrganization::create([
            'purch_org_id'  => (string) Str::uuid(),
            'tenant_id'     => $tenantId,
            'code'          => $code,
            'name'          => $name,
            'description'   => $description,
            'company_id'    => $companyId,
            'is_active'     => $isActive,
            'is_reference'  => $isReference,
            'row_version'   => 1,
        ]);
    }

    public function update(
        string $purchOrgId,
        string $code,
        string $name,
        ?string $companyId = null,
        ?string $description = null,
        ?bool $isActive = null,
        ?bool $isReference = null
    ): PurchasingOrganization {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = PurchasingOrganization::where('tenant_id', $tenantId)
            ->where('purch_org_id', $purchOrgId)
            ->firstOrFail();

        if ($row->code !== $code) {
            if (PurchasingOrganization::where('tenant_id', $tenantId)
                ->where('code', $code)
                ->where('purch_org_id', '!=', $purchOrgId)
                ->exists()) {
                throw new DomainException('کد سازمان خرید تکراری است.', 'duplicate_purch_org_code');
            }
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
        if ($isReference !== null) {
            $payload['is_reference'] = $isReference;
        }

        $row->update($payload);

        return $row->fresh(['assignments', 'company']) ?? $row;
    }

    public function softDelete(string $purchOrgId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = PurchasingOrganization::where('tenant_id', $tenantId)
            ->where('purch_org_id', $purchOrgId)
            ->firstOrFail();

        $row->delete();
    }

    public function restore(string $purchOrgId): PurchasingOrganization
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = PurchasingOrganization::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('purch_org_id', $purchOrgId)
            ->firstOrFail();

        if (PurchasingOrganization::where('tenant_id', $tenantId)->where('code', $row->code)->exists()) {
            throw new DomainException(
                'کد این سازمان خرید با یک رکورد فعال دیگر تداخل دارد.',
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

    public function listAssignments(string $purchOrgId)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        PurchasingOrganization::where('tenant_id', $tenantId)->where('purch_org_id', $purchOrgId)->firstOrFail();

        return PurchOrgAssignment::where('tenant_id', $tenantId)
            ->where('purch_org_id', $purchOrgId)
            ->orderBy('created_at')
            ->get();
    }

    public function assign(string $purchOrgId, ?string $companyId = null, ?string $branchId = null): PurchOrgAssignment
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        PurchasingOrganization::where('tenant_id', $tenantId)->where('purch_org_id', $purchOrgId)->firstOrFail();

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

        $existing = PurchOrgAssignment::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('purch_org_id', $purchOrgId)
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

        return PurchOrgAssignment::create([
            'assignment_id' => (string) Str::uuid(),
            'tenant_id'     => $tenantId,
            'purch_org_id'  => $purchOrgId,
            'company_id'    => $companyId,
            'branch_id'     => $branchId,
            'is_active'     => true,
            'row_version'   => 1,
        ]);
    }

    public function unassign(string $assignmentId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = PurchOrgAssignment::where('tenant_id', $tenantId)
            ->where('assignment_id', $assignmentId)
            ->firstOrFail();

        $row->delete();
    }
}
