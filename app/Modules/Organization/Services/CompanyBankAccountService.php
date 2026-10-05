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
        $holder = $data['account_holder_name'] ?? $data['label'] ?? null;

        return DB::transaction(function () use ($tenantId, $companyId, $data, $isPrimary, $holder) {
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
                'account_holder_name'  => $holder,
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

    public function update(string $bankAccountId, array $data): CompanyBankAccount
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $row = CompanyBankAccount::where('tenant_id', $tenantId)
            ->where('bank_account_id', $bankAccountId)
            ->firstOrFail();

        $isPrimary = array_key_exists('is_primary', $data)
            ? (bool) $data['is_primary']
            : (bool) $row->is_primary;

        $holder = $data['account_holder_name'] ?? $data['label'] ?? $row->account_holder_name;

        return DB::transaction(function () use ($tenantId, $row, $data, $isPrimary, $holder) {
            if ($isPrimary && ! $row->is_primary) {
                CompanyBankAccount::where('tenant_id', $tenantId)
                    ->where('company_id', $row->company_id)
                    ->where('is_primary', true)
                    ->where('bank_account_id', '!=', $row->bank_account_id)
                    ->update(['is_primary' => false]);
            }

            $row->bank_name = $data['bank_name'] ?? $row->bank_name;
            $row->account_holder_name = $holder;
            $row->account_number = $data['account_number'] ?? $row->account_number;
            $row->iban = array_key_exists('iban', $data) ? ($data['iban'] ?: null) : $row->iban;
            $row->swift_bic = array_key_exists('swift_bic', $data) ? ($data['swift_bic'] ?: null) : $row->swift_bic;
            $row->currency_id = array_key_exists('currency_id', $data) ? ($data['currency_id'] ?: null) : $row->currency_id;
            $row->branch_name = array_key_exists('branch_name', $data) ? ($data['branch_name'] ?: null) : $row->branch_name;
            $row->is_primary = $isPrimary;
            if (array_key_exists('is_active', $data)) {
                $row->is_active = (bool) $data['is_active'];
            }
            if (array_key_exists('notes', $data)) {
                $row->notes = $data['notes'] ?: null;
            }
            $row->row_version = (int) $row->row_version + 1;
            $row->save();

            return $row->fresh();
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
