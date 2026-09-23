<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\IntercompanyPartner;
use App\Modules\Organization\Models\IntercompanyRule;
use App\Base\Context\TenantContext;
use Illuminate\Support\Str;

class IntercompanyService
{
    public function mapPartners(
        string $fromCompanyId,
        string $toCompanyId,
        ?string $partnerCustomerId = null,
        ?string $partnerVendorId = null,
        ?string $notes = null,
    ): IntercompanyPartner {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if ($fromCompanyId === $toCompanyId) {
            throw new \Exception('شرکت مبدأ و مقصد نمی‌توانند یکسان باشند.');
        }

        Company::where('tenant_id', $tenantId)->where('company_id', $fromCompanyId)->firstOrFail();
        Company::where('tenant_id', $tenantId)->where('company_id', $toCompanyId)->firstOrFail();

        $existing = IntercompanyPartner::where('tenant_id', $tenantId)
            ->where('from_company_id', $fromCompanyId)
            ->where('to_company_id', $toCompanyId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return IntercompanyPartner::create([
            'ic_partner_id'        => (string) Str::uuid(),
            'tenant_id'            => $tenantId,
            'from_company_id'      => $fromCompanyId,
            'to_company_id'        => $toCompanyId,
            'partner_customer_id'  => $partnerCustomerId,
            'partner_vendor_id'    => $partnerVendorId,
            'is_active'            => true,
            'notes'                => $notes,
            'row_version'          => 1,
        ]);
    }

    public function createRule(
        string $code,
        string $name,
        string $sourceDocType,
        string $targetDocType,
        bool $autoCreateMirror = true,
    ): IntercompanyRule {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (IntercompanyRule::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new \Exception('کد قانون بین‌شرکتی تکراری است.');
        }

        return IntercompanyRule::create([
            'ic_rule_id'         => (string) Str::uuid(),
            'tenant_id'          => $tenantId,
            'code'               => $code,
            'name'               => $name,
            'source_doc_type'    => strtoupper($sourceDocType),
            'target_doc_type'    => strtoupper($targetDocType),
            'auto_create_mirror' => $autoCreateMirror,
            'is_active'          => true,
            'row_version'        => 1,
        ]);
    }

    public function listPartners()
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return IntercompanyPartner::where('tenant_id', $tenantId)->orderBy('created_at')->get();
    }

    public function listRules()
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return IntercompanyRule::where('tenant_id', $tenantId)->orderBy('code')->get();
    }
}
