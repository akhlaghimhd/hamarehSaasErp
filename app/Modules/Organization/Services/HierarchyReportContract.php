<?php

namespace App\Modules\Organization\Services;

/**
 * D2 — Report contracts for hierarchy consumers.
 * Operational modules MUST declare purpose + fallback; hierarchy is never the only path.
 */
final class HierarchyReportContract
{
    /**
     * @return list<array{
     *   capability: string,
     *   purpose: string,
     *   fallback: string,
     *   owner_module: string,
     *   status: string
     * }>
     */
    public static function contracts(): array
    {
        return [
            [
                'capability' => 'finance_consolidation_group',
                'purpose' => 'LEGAL',
                'fallback' => 'parent_company_id chain on erp_companies',
                'owner_module' => 'Accounting',
                'status' => 'contract_only',
            ],
            [
                'capability' => 'logistics_site_rollup',
                'purpose' => 'ESTABLISHMENT',
                'fallback' => 'direct branch.company_id',
                'owner_module' => 'Inventory/Logistics',
                'status' => 'contract_only',
            ],
            [
                'capability' => 'product_line_pnl',
                'purpose' => 'CUSTOM',
                'fallback' => 'business unit primary company assignment (erp_business_unit_companies)',
                'owner_module' => 'Accounting/Reporting',
                'status' => 'contract_only',
            ],
            [
                'capability' => 'management_org_chart',
                'purpose' => 'MANAGEMENT',
                'fallback' => 'company + department + cost_center direct FKs',
                'owner_module' => 'Organization',
                'status' => 'contract_only',
            ],
            [
                'capability' => 'tax_group_view',
                'purpose' => 'TAX',
                'fallback' => 'LEGAL tree or parent_company_id (TAX not full-auto in v1)',
                'owner_module' => 'Accounting',
                'status' => 'contract_only',
            ],
        ];
    }
}
