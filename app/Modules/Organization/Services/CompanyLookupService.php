<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;

/**
 * Lookup service for Company – owned by Organization module.
 * Other modules must use this Service (not the Company model directly).
 */
class CompanyLookupService
{
    public function findById(string $companyId): ?object
    {
        return Company::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();
    }

    public function exists(string $companyId): bool
    {
        return Company::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->exists();
    }

    public function getBasicInfo(string $companyId): ?array
    {
        $company = $this->findById($companyId);

        if (!$company) {
            return null;
        }

        return [
            'company_id' => $company->company_id,
            'code'       => $company->code,
            'name'       => $company->name,
            'is_active'  => $company->is_active,
        ];
    }
}
