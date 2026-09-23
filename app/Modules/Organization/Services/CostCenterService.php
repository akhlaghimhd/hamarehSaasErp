<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\CostCenter;
use App\Base\Context\TenantContext;
use Illuminate\Support\Str;

class CostCenterService
{
    public function create(array $data): CostCenter
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $companyId = $data['company_id'];

        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        if (CostCenter::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('code', $data['code'])
            ->exists()) {
            throw new \Exception('کد مرکز هزینه در این شرکت تکراری است.');
        }

        if (!empty($data['parent_cost_center_id'])) {
            $parent = CostCenter::where('tenant_id', $tenantId)
                ->where('cost_center_id', $data['parent_cost_center_id'])
                ->first();
            if (!$parent || $parent->company_id !== $companyId) {
                throw new \Exception('مرکز هزینه والد نامعتبر است.');
            }
        }

        return CostCenter::create([
            'cost_center_id'        => (string) Str::uuid(),
            'tenant_id'             => $tenantId,
            'company_id'            => $companyId,
            'department_id'         => $data['department_id'] ?? null,
            'parent_cost_center_id' => $data['parent_cost_center_id'] ?? null,
            'code'                  => $data['code'],
            'name'                  => $data['name'],
            'is_active'             => (bool) ($data['is_active'] ?? true),
            'row_version'           => 1,
        ]);
    }

    public function listForCompany(string $companyId)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        return CostCenter::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();
    }
}
