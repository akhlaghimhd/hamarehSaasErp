<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\CompanyBankAccount;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyBankAccountService
{
    public function create(array $data): CompanyBankAccount
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $companyId = $data['company_id'];

        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        $isPrimary = (bool) ($data['is_primary'] ?? false);

        return DB::transaction(function () use ($tenantId, $companyId, $data, $isPrimary) {
            if ($isPrimary) {
                CompanyBankAccount::where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            return CompanyBankAccount::create([
                'bank_account_id'      => (string) Str::uuid(),
                'tenant_id'            => $tenantId,
                'company_id'           => $companyId,
                'bank_name'            => $data['bank_name'],
                'account_holder_name'  => $data['account_holder_name'] ?? null,
                'account_number'       => $data['account_number'],
                'iban'                 => $data['iban'] ?? null,
                'swift_bic'            => $data['swift_bic'] ?? null,
                'currency_id'          => $data['currency_id'] ?? null,
                'branch_name'          => $data['branch_name'] ?? null,
                'is_primary'           => $isPrimary,
                'is_active'            => (bool) ($data['is_active'] ?? true),
                'notes'                => $data['notes'] ?? null,
                'row_version'          => 1,
            ]);
        });
    }

    public function listForCompany(string $companyId)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();

        return CompanyBankAccount::where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();
    }

    public function softDelete(string $bankAccountId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = CompanyBankAccount::where('tenant_id', $tenantId)
            ->where('bank_account_id', $bankAccountId)
            ->firstOrFail();
        $row->delete();
    }
}
