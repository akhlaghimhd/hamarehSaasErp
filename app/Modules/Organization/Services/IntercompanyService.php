<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\IntercompanyPartner;
use App\Modules\Organization\Models\IntercompanyRule;
use App\Base\Context\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IntercompanyService
{
    /**
     * Stable document-type catalog for IC mirror rules (Org config).
     * Operational engines (Sales/Purch/Accounting) consume these codes later.
     *
     * @return list<array{code: string, label_fa: string, label_en: string, domain: string}>
     */
    public function documentTypeCatalog(): array
    {
        return [
            ['code' => 'SO', 'label_fa' => 'سفارش فروش', 'label_en' => 'Sales Order', 'domain' => 'sales'],
            ['code' => 'PO', 'label_fa' => 'سفارش خرید', 'label_en' => 'Purchase Order', 'domain' => 'purchasing'],
            ['code' => 'INV', 'label_fa' => 'فاکتور فروش', 'label_en' => 'Sales Invoice', 'domain' => 'sales'],
            ['code' => 'BILL', 'label_fa' => 'فاکتور خرید / صورتحساب فروشنده', 'label_en' => 'Vendor Bill', 'domain' => 'purchasing'],
            ['code' => 'CN', 'label_fa' => 'سند بستانکار فروش', 'label_en' => 'Credit Note', 'domain' => 'sales'],
            ['code' => 'DN', 'label_fa' => 'سند بدهکار خرید', 'label_en' => 'Debit Note', 'domain' => 'purchasing'],
            ['code' => 'RMA', 'label_fa' => 'مجوز برگشت از مشتری', 'label_en' => 'Return Authorization', 'domain' => 'sales'],
            ['code' => 'VRMA', 'label_fa' => 'مجوز برگشت به فروشنده', 'label_en' => 'Vendor Return Authorization', 'domain' => 'purchasing'],
            ['code' => 'IC_JE', 'label_fa' => 'سند حسابداری بین‌شرکتی', 'label_en' => 'Intercompany Journal Entry', 'domain' => 'accounting'],
            ['code' => 'IC_ST', 'label_fa' => 'انتقال موجودی بین‌شرکتی', 'label_en' => 'Intercompany Stock Transfer', 'domain' => 'inventory'],
        ];
    }

    public function mapPartners(
        string $fromCompanyId,
        string $toCompanyId,
        ?string $partnerCustomerId = null,
        ?string $partnerVendorId = null,
        ?string $notes = null,
        bool $isActive = true,
    ): IntercompanyPartner {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if ($fromCompanyId === $toCompanyId) {
            throw new \Exception('شرکت مبدأ و مقصد نمی‌توانند یکسان باشند.');
        }

        $this->assertCompanyInTenant($tenantId, $fromCompanyId);
        $this->assertCompanyInTenant($tenantId, $toCompanyId);

        $existing = IntercompanyPartner::where('tenant_id', $tenantId)
            ->where('from_company_id', $fromCompanyId)
            ->where('to_company_id', $toCompanyId)
            ->first();

        if ($existing) {
            $existing->partner_customer_id = $partnerCustomerId ?? $existing->partner_customer_id;
            $existing->partner_vendor_id = $partnerVendorId ?? $existing->partner_vendor_id;
            if ($notes !== null) {
                $existing->notes = $notes;
            }
            $existing->is_active = $isActive;
            $existing->row_version = (int) $existing->row_version + 1;
            $existing->save();

            return $existing->fresh();
        }

        return IntercompanyPartner::create([
            'ic_partner_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'from_company_id' => $fromCompanyId,
            'to_company_id' => $toCompanyId,
            'partner_customer_id' => $partnerCustomerId,
            'partner_vendor_id' => $partnerVendorId,
            'is_active' => $isActive,
            'notes' => $notes,
            'row_version' => 1,
        ]);
    }

    public function updatePartner(
        string $icPartnerId,
        array $attrs,
    ): IntercompanyPartner {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = IntercompanyPartner::where('tenant_id', $tenantId)
            ->where('ic_partner_id', $icPartnerId)
            ->firstOrFail();

        if (array_key_exists('from_company_id', $attrs) || array_key_exists('to_company_id', $attrs)) {
            $from = $attrs['from_company_id'] ?? $row->from_company_id;
            $to = $attrs['to_company_id'] ?? $row->to_company_id;
            if ($from === $to) {
                throw new \Exception('شرکت مبدأ و مقصد نمی‌توانند یکسان باشند.');
            }
            $this->assertCompanyInTenant($tenantId, $from);
            $this->assertCompanyInTenant($tenantId, $to);
            $dup = IntercompanyPartner::where('tenant_id', $tenantId)
                ->where('from_company_id', $from)
                ->where('to_company_id', $to)
                ->where('ic_partner_id', '!=', $icPartnerId)
                ->exists();
            if ($dup) {
                throw new \Exception('این جفت شرکت قبلاً به‌عنوان شریک ثبت شده است.');
            }
            $row->from_company_id = $from;
            $row->to_company_id = $to;
        }

        foreach (['partner_customer_id', 'partner_vendor_id', 'notes'] as $key) {
            if (array_key_exists($key, $attrs)) {
                $row->{$key} = $attrs[$key];
            }
        }
        if (array_key_exists('is_active', $attrs)) {
            $row->is_active = (bool) $attrs['is_active'];
        }

        $row->row_version = (int) $row->row_version + 1;
        $row->save();

        return $row->fresh();
    }

    public function softDeletePartner(string $icPartnerId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = IntercompanyPartner::where('tenant_id', $tenantId)
            ->where('ic_partner_id', $icPartnerId)
            ->firstOrFail();
        $row->delete();
    }

    public function createRule(
        string $code,
        string $name,
        string $sourceDocType,
        string $targetDocType,
        bool $autoCreateMirror = true,
        bool $isActive = true,
        ?string $notes = null,
    ): IntercompanyRule {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (IntercompanyRule::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new \Exception('کد قانون بین‌شرکتی تکراری است.');
        }

        $this->assertKnownDocType($sourceDocType);
        $this->assertKnownDocType($targetDocType);

        return IntercompanyRule::create([
            'ic_rule_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'source_doc_type' => strtoupper($sourceDocType),
            'target_doc_type' => strtoupper($targetDocType),
            'auto_create_mirror' => $autoCreateMirror,
            'is_active' => $isActive,
            'notes' => $notes,
            'row_version' => 1,
        ]);
    }

    public function updateRule(string $icRuleId, array $attrs): IntercompanyRule
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = IntercompanyRule::where('tenant_id', $tenantId)
            ->where('ic_rule_id', $icRuleId)
            ->firstOrFail();

        if (array_key_exists('code', $attrs) && $attrs['code'] !== $row->code) {
            if (IntercompanyRule::where('tenant_id', $tenantId)->where('code', $attrs['code'])->exists()) {
                throw new \Exception('کد قانون بین‌شرکتی تکراری است.');
            }
            $row->code = $attrs['code'];
        }
        if (array_key_exists('name', $attrs)) {
            $row->name = $attrs['name'];
        }
        if (array_key_exists('source_doc_type', $attrs)) {
            $this->assertKnownDocType($attrs['source_doc_type']);
            $row->source_doc_type = strtoupper($attrs['source_doc_type']);
        }
        if (array_key_exists('target_doc_type', $attrs)) {
            $this->assertKnownDocType($attrs['target_doc_type']);
            $row->target_doc_type = strtoupper($attrs['target_doc_type']);
        }
        if (array_key_exists('auto_create_mirror', $attrs)) {
            $row->auto_create_mirror = (bool) $attrs['auto_create_mirror'];
        }
        if (array_key_exists('is_active', $attrs)) {
            $row->is_active = (bool) $attrs['is_active'];
        }
        if (array_key_exists('notes', $attrs)) {
            $row->notes = $attrs['notes'];
        }

        $row->row_version = (int) $row->row_version + 1;
        $row->save();

        return $row->fresh();
    }

    public function softDeleteRule(string $icRuleId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $row = IntercompanyRule::where('tenant_id', $tenantId)
            ->where('ic_rule_id', $icRuleId)
            ->firstOrFail();
        $row->delete();
    }

    public function listPartners(): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $rows = IntercompanyPartner::where('tenant_id', $tenantId)
            ->orderBy('created_at')
            ->get();

        $companyIds = $rows->pluck('from_company_id')
            ->merge($rows->pluck('to_company_id'))
            ->unique()
            ->values();

        $names = Company::where('tenant_id', $tenantId)
            ->whereIn('company_id', $companyIds)
            ->get(['company_id', 'name', 'legal_name', 'code'])
            ->keyBy('company_id');

        return $rows->map(function (IntercompanyPartner $p) use ($names) {
            $from = $names->get($p->from_company_id);
            $to = $names->get($p->to_company_id);

            return [
                'ic_partner_id' => $p->ic_partner_id,
                'from_company_id' => $p->from_company_id,
                'to_company_id' => $p->to_company_id,
                'from_company_name' => $from?->name ?? $from?->legal_name,
                'from_company_code' => $from?->code ?? null,
                'to_company_name' => $to?->name ?? $to?->legal_name,
                'to_company_code' => $to?->code ?? null,
                'partner_customer_id' => $p->partner_customer_id,
                'partner_vendor_id' => $p->partner_vendor_id,
                'is_active' => (bool) $p->is_active,
                'notes' => $p->notes,
                'row_version' => (int) $p->row_version,
                'created_at' => $p->created_at?->toIso8601String(),
                'updated_at' => $p->updated_at?->toIso8601String(),
            ];
        });
    }

    public function listRules(): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return IntercompanyRule::where('tenant_id', $tenantId)
            ->orderBy('code')
            ->get()
            ->map(fn (IntercompanyRule $r) => [
                'ic_rule_id' => $r->ic_rule_id,
                'code' => $r->code,
                'name' => $r->name,
                'source_doc_type' => $r->source_doc_type,
                'target_doc_type' => $r->target_doc_type,
                'auto_create_mirror' => (bool) $r->auto_create_mirror,
                'is_active' => (bool) $r->is_active,
                'notes' => $r->notes,
                'row_version' => (int) $r->row_version,
                'created_at' => $r->created_at?->toIso8601String(),
                'updated_at' => $r->updated_at?->toIso8601String(),
            ]);
    }

    private function assertCompanyInTenant(string $tenantId, string $companyId): void
    {
        Company::where('tenant_id', $tenantId)->where('company_id', $companyId)->firstOrFail();
    }

    private function assertKnownDocType(string $code): void
    {
        $upper = strtoupper($code);
        $known = array_column($this->documentTypeCatalog(), 'code');
        if (! in_array($upper, $known, true)) {
            throw new \Exception("نوع سند «{$code}» در کاتالوگ بین‌شرکتی تعریف نشده است.");
        }
    }
}
