<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Rich demo org data for the demo tenant (Aria Sanat group).
 * Idempotent upserts by (tenant_id, code) where applicable.
 * Target: at least 4 samples per major organization entity family.
 */
class AriaSanatDemoOrgSeeder extends Seeder
{
    public const DEMO_TENANT_ID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();
        if (!$tenantId) {
            $this->command?->warn('AriaSanatDemoOrgSeeder: no tenant found — skip.');

            return;
        }

        // --- Companies (4+) ---
        $hqId = $this->ensureAriaHq($tenantId);
        $cPakhsh = $this->upsertCompany($tenantId, [
            'code' => 'ARYA-SUB', 'name' => 'آریا پخش', 'legal_name' => 'شرکت آریا پخش',
            'trade_name' => 'آریا پخش', 'registration_number' => '654321',
            'economic_code' => '42222222222', 'tax_identifier' => '14007654321',
            'entity_kind' => 'OPERATING', 'is_primary' => false, 'parent_company_id' => $hqId,
            'is_active' => true, 'status' => 1,
        ], adoptPrimary: false);
        $cService = $this->upsertCompany($tenantId, [
            'code' => 'ARYA-SVC', 'name' => 'آریا خدمات', 'legal_name' => 'شرکت آریا خدمات فنی',
            'trade_name' => 'آریا خدمات', 'registration_number' => '778899',
            'economic_code' => '43333333333', 'tax_identifier' => '14008887766',
            'entity_kind' => 'OPERATING', 'is_primary' => false, 'parent_company_id' => $hqId,
            'is_active' => true, 'status' => 1,
        ], adoptPrimary: false);
        $cHold = $this->upsertCompany($tenantId, [
            'code' => 'ARYA-HOLD', 'name' => 'آریا هلدینگ', 'legal_name' => 'شرکت آریا سرمایه‌گذاری',
            'trade_name' => 'آریا هلدینگ', 'registration_number' => '112233',
            'economic_code' => '44444444444', 'tax_identifier' => '14001112233',
            'entity_kind' => 'CONSOLIDATION', 'is_primary' => false, 'parent_company_id' => $hqId,
            'is_active' => true, 'status' => 1,
        ], adoptPrimary: false);

        $this->upsertOwnership($tenantId, $cPakhsh, $hqId, 80.0);
        $this->upsertOwnership($tenantId, $cService, $hqId, 100.0);
        $this->upsertOwnership($tenantId, $cHold, $hqId, 60.0);

        // --- Branches (4+) ---
        $brHq = $this->upsertBranch($tenantId, $hqId, 'ARYA-HQ-BR1', 'دفتر مرکزی تهران', 'OFFICE');
        $brNorth = $this->upsertBranch($tenantId, $hqId, 'ARYA-HQ-BR2', 'شعبه شمال', 'BRANCH');
        $brPakhsh = $this->upsertBranch($tenantId, $cPakhsh, 'ARYA-SUB-BR1', 'انبار پخش کرج', 'WAREHOUSE');
        $brSvc = $this->upsertBranch($tenantId, $cService, 'ARYA-SVC-BR1', 'مرکز خدمات اصفهان', 'BRANCH');

        // --- Departments (4+) ---
        $depSales = $this->upsertDepartment($tenantId, $hqId, $brHq, 'DEP-SALES', 'فروش');
        $depFin = $this->upsertDepartment($tenantId, $hqId, $brHq, 'DEP-FIN', 'مالی');
        $depHr = $this->upsertDepartment($tenantId, $hqId, $brNorth, 'DEP-HR', 'منابع انسانی');
        $depOps = $this->upsertDepartment($tenantId, $cPakhsh, $brPakhsh, 'DEP-OPS', 'عملیات انبار');

        // --- Business units (4+) ---
        $buOps = $this->upsertBusinessUnit($tenantId, 'ARYA-BU-OPS', 'عملیات تولید و پخش', 'خط اصلی تولید و توزیع');
        $buHome = $this->upsertBusinessUnit($tenantId, 'ARYA-BU-HOME', 'لوازم خانگی', 'محصولات خانگی');
        $buInd = $this->upsertBusinessUnit($tenantId, 'ARYA-BU-IND', 'صنعتی', 'تجهیزات صنعتی');
        $buSvc = $this->upsertBusinessUnit($tenantId, 'ARYA-BU-SVC', 'خدمات پس از فروش', 'تعمیر و نگهداری');
        $this->assignCompanyToBu($tenantId, $buOps, $hqId, true);
        $this->assignCompanyToBu($tenantId, $buHome, $hqId, true);
        $this->assignCompanyToBu($tenantId, $buInd, $hqId, true);
        $this->assignCompanyToBu($tenantId, $buSvc, $cService, true);
        $this->assignCompanyToBu($tenantId, $buOps, $cPakhsh, false);

        // --- Cost centers (4+) ---
        $this->upsertCostCenter($tenantId, $hqId, $depSales, 'CC-SALES', 'مرکز هزینه فروش');
        $this->upsertCostCenter($tenantId, $hqId, $depFin, 'CC-FIN', 'مرکز هزینه مالی');
        $this->upsertCostCenter($tenantId, $hqId, $depHr, 'CC-HR', 'مرکز هزینه منابع انسانی');
        $this->upsertCostCenter($tenantId, $cPakhsh, $depOps, 'CC-WH', 'مرکز هزینه انبار');

        // --- Hierarchies (4) ---
        $hLegal = $this->upsertHierarchy($tenantId, 'ARYA-LEGAL', 'ساختار حقوقی آریا صنعت', 'LEGAL');
        $hMgmt = $this->upsertHierarchy($tenantId, 'ARYA-MGMT', 'ساختار مدیریتی', 'MANAGEMENT');
        $hTax = $this->upsertHierarchy($tenantId, 'ARYA-TAX', 'گروه مالیاتی', 'TAX');
        $hEst = $this->upsertHierarchy($tenantId, 'ARYA-EST', 'استقرار شعب', 'ESTABLISHMENT');

        $nHq = $this->upsertHierarchyNode($tenantId, $hLegal, 'COMPANY', $hqId, null, 10);
        $this->upsertHierarchyNode($tenantId, $hLegal, 'COMPANY', $cPakhsh, $nHq, 20);
        $this->upsertHierarchyNode($tenantId, $hLegal, 'COMPANY', $cService, $nHq, 30);
        $this->upsertHierarchyNode($tenantId, $hLegal, 'COMPANY', $cHold, $nHq, 40);

        $nMgmtRoot = $this->upsertHierarchyNode($tenantId, $hMgmt, 'COMPANY', $hqId, null, 10);
        $this->upsertHierarchyNode($tenantId, $hMgmt, 'BUSINESS_UNIT', $buOps, $nMgmtRoot, 20);
        $this->upsertHierarchyNode($tenantId, $hMgmt, 'BUSINESS_UNIT', $buHome, $nMgmtRoot, 30);
        $this->upsertHierarchyNode($tenantId, $hMgmt, 'DEPARTMENT', $depSales, $nMgmtRoot, 40);

        $this->upsertHierarchyNode($tenantId, $hTax, 'COMPANY', $hqId, null, 10);
        $this->upsertHierarchyNode($tenantId, $hTax, 'COMPANY', $cPakhsh, null, 20);

        $nEst = $this->upsertHierarchyNode($tenantId, $hEst, 'COMPANY', $hqId, null, 10);
        $this->upsertHierarchyNode($tenantId, $hEst, 'BRANCH', $brHq, $nEst, 20);
        $this->upsertHierarchyNode($tenantId, $hEst, 'BRANCH', $brNorth, $nEst, 30);
        $this->upsertHierarchyNode($tenantId, $hEst, 'BRANCH', $brPakhsh, $nEst, 40);

        // --- Sales / Purchasing orgs (4 each) ---
        $this->upsertSalesOrg($tenantId, $hqId, 'SO-DOM', 'فروش داخلی');
        $this->upsertSalesOrg($tenantId, $hqId, 'SO-EXP', 'فروش صادرات');
        $this->upsertSalesOrg($tenantId, $cPakhsh, 'SO-DIST', 'توزیع پخش');
        $this->upsertSalesOrg($tenantId, $cService, 'SO-SVC', 'فروش خدمات');

        $this->upsertPurchOrg($tenantId, $hqId, 'PO-RAW', 'خرید مواد');
        $this->upsertPurchOrg($tenantId, $hqId, 'PO-GEN', 'خرید عمومی');
        $this->upsertPurchOrg($tenantId, $cPakhsh, 'PO-DIST', 'خرید پخش');
        $this->upsertPurchOrg($tenantId, $cService, 'PO-SVC', 'خرید خدمات');

        // --- Intercompany partners (pairs) ---
        $this->upsertIcPartner($tenantId, $hqId, $cPakhsh);
        $this->upsertIcPartner($tenantId, $hqId, $cService);
        $this->upsertIcPartner($tenantId, $cPakhsh, $cService);
        $this->upsertIcPartner($tenantId, $hqId, $cHold);

        $this->command?->info('Aria Sanat demo org refreshed on tenant '.$tenantId);
        $this->command?->info('  Companies: HQ + SUB + SVC + HOLD');
        $this->command?->info('  Branches / Depts / BUs / CostCenters / Hierarchies / Sales / Purch / IC seeded (4+ each where applicable).');
    }

    private function resolveTenantId(): ?string
    {
        $fromEnv = env('DEMO_TENANT_ID');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return DB::table('tenants')->where('tenant_id', $fromEnv)->exists() ? $fromEnv : null;
        }
        if (DB::table('tenants')->where('tenant_id', self::DEMO_TENANT_ID)->exists()) {
            return self::DEMO_TENANT_ID;
        }

        return DB::table('tenants')->orderBy('created_at')->value('tenant_id');
    }

    private function ensureAriaHq(string $tenantId): string
    {
        $attrs = [
            'code' => 'ARYA-HQ', 'name' => 'آریا صنعت',
            'legal_name' => 'شرکت آریا صنعت (سهامی خاص)', 'trade_name' => 'آریا صنعت',
            'registration_number' => '123456', 'economic_code' => '41111111111',
            'tax_identifier' => '14001234567', 'entity_kind' => 'OPERATING',
            'is_primary' => true, 'parent_company_id' => null,
            'is_active' => true, 'status' => 1,
        ];

        $byCode = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)->where('code', 'ARYA-HQ')->whereNull('deleted_at')->first();
        if ($byCode) {
            return $this->upsertCompany($tenantId, $attrs, adoptPrimary: true);
        }

        $primary = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)->where('is_primary', true)->whereNull('deleted_at')->first();
        if ($primary) {
            $attrs['code'] = $primary->code ?? 'ARYA-HQ';

            return $this->upsertCompany($tenantId, array_merge((array) $primary, $attrs), adoptPrimary: true);
        }

        return $this->upsertCompany($tenantId, $attrs, adoptPrimary: true);
    }

    private function upsertCompany(string $tenantId, array $attrs, bool $adoptPrimary = false): string
    {
        $code = $attrs['code'];
        $existing = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)->where('code', $code)->whereNull('deleted_at')->first();

        $row = array_merge([
            'tenant_id' => $tenantId,
            'updated_at' => now(),
        ], $attrs);

        if ($adoptPrimary) {
            DB::table('erp_companies')->where('tenant_id', $tenantId)->where('is_primary', true)
                ->whereNull('deleted_at')->update(['is_primary' => false, 'updated_at' => now()]);
            $row['is_primary'] = true;
        }

        $row = $this->filterColumns('erp_companies', $row);

        if ($existing) {
            unset($row['tenant_id']);
            DB::table('erp_companies')->where('company_id', $existing->company_id)->update($row);

            return (string) $existing->company_id;
        }

        $id = (string) Str::uuid();
        $row['company_id'] = $id;
        $row['created_at'] = now();
        if (!isset($row['row_version'])) {
            $row['row_version'] = 1;
        }
        $row = $this->filterColumns('erp_companies', $row);
        DB::table('erp_companies')->insert($row);

        return $id;
    }

    private function filterColumns(string $table, array $row): array
    {
        foreach (array_keys($row) as $col) {
            if (!Schema::hasColumn($table, $col)) {
                unset($row[$col]);
            }
        }

        return $row;
    }

    private function upsertOwnership(string $tenantId, string $companyId, string $ownerCompanyId, float $percent): void
    {
        if (!Schema::hasTable('erp_company_ownerships')) {
            return;
        }
        $exists = DB::table('erp_company_ownerships')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('owner_company_id', $ownerCompanyId)
            ->whereNull('deleted_at')
            ->exists();
        if ($exists) {
            return;
        }
        $row = $this->filterColumns('erp_company_ownerships', [
            'ownership_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'owner_company_id' => $ownerCompanyId,
            'ownership_percent' => $percent,
            'relation_type' => 'SUBSIDIARY',
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);
        DB::table('erp_company_ownerships')->insert($row);
    }

    private function upsertBranch(string $tenantId, string $companyId, string $code, string $name, string $kind): string
    {
        if (!Schema::hasTable('erp_branches')) {
            return 'n/a';
        }
        $existing = DB::table('erp_branches')
            ->where('tenant_id', $tenantId)->where('company_id', $companyId)->where('code', $code)
            ->whereNull('deleted_at')->first();
        $row = $this->filterColumns('erp_branches', [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'branch_kind' => $kind,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        if ($existing) {
            unset($row['tenant_id']);
            DB::table('erp_branches')->where('branch_id', $existing->branch_id)->update($row);

            return (string) $existing->branch_id;
        }
        $id = (string) Str::uuid();
        $row['branch_id'] = $id;
        $row['created_at'] = now();
        $row['row_version'] = 1;
        $row = $this->filterColumns('erp_branches', $row);
        DB::table('erp_branches')->insert($row);

        return $id;
    }

    private function upsertDepartment(string $tenantId, string $companyId, string $branchId, string $code, string $name): string
    {
        if (!Schema::hasTable('erp_departments') || $branchId === 'n/a') {
            return 'n/a';
        }
        $existing = DB::table('erp_departments')
            ->where('tenant_id', $tenantId)->where('code', $code)->whereNull('deleted_at')->first();
        $row = $this->filterColumns('erp_departments', [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        if ($existing) {
            unset($row['tenant_id']);
            DB::table('erp_departments')->where('department_id', $existing->department_id)->update($row);

            return (string) $existing->department_id;
        }
        $id = (string) Str::uuid();
        $row['department_id'] = $id;
        $row['created_at'] = now();
        $row['row_version'] = 1;
        $row = $this->filterColumns('erp_departments', $row);
        DB::table('erp_departments')->insert($row);

        return $id;
    }

    private function upsertBusinessUnit(string $tenantId, string $code, string $name, ?string $description = null): string
    {
        if (!Schema::hasTable('erp_business_units')) {
            return 'n/a';
        }
        $existing = DB::table('erp_business_units')
            ->where('tenant_id', $tenantId)->where('code', $code)->whereNull('deleted_at')->first();
        $row = $this->filterColumns('erp_business_units', [
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        if ($existing) {
            unset($row['tenant_id']);
            DB::table('erp_business_units')->where('business_unit_id', $existing->business_unit_id)->update($row);

            return (string) $existing->business_unit_id;
        }
        $id = (string) Str::uuid();
        $row['business_unit_id'] = $id;
        $row['created_at'] = now();
        $row['row_version'] = 1;
        $row = $this->filterColumns('erp_business_units', $row);
        DB::table('erp_business_units')->insert($row);

        return $id;
    }

    private function assignCompanyToBu(string $tenantId, string $buId, string $companyId, bool $isPrimary = false): void
    {
        if (!Schema::hasTable('erp_business_unit_companies') || $buId === 'n/a') {
            return;
        }
        $exists = DB::table('erp_business_unit_companies')
            ->where('tenant_id', $tenantId)
            ->where('business_unit_id', $buId)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->exists();
        if ($exists) {
            if ($isPrimary) {
                DB::table('erp_business_unit_companies')
                    ->where('tenant_id', $tenantId)->where('business_unit_id', $buId)
                    ->update(['is_primary' => false]);
                DB::table('erp_business_unit_companies')
                    ->where('tenant_id', $tenantId)->where('business_unit_id', $buId)->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->update(['is_primary' => true, 'updated_at' => now()]);
            }

            return;
        }
        if ($isPrimary) {
            DB::table('erp_business_unit_companies')
                ->where('tenant_id', $tenantId)->where('business_unit_id', $buId)
                ->whereNull('deleted_at')
                ->update(['is_primary' => false]);
        }
        $row = $this->filterColumns('erp_business_unit_companies', [
            'assignment_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'business_unit_id' => $buId,
            'company_id' => $companyId,
            'is_primary' => $isPrimary,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);
        DB::table('erp_business_unit_companies')->insert($row);
    }

    private function upsertCostCenter(string $tenantId, string $companyId, string $departmentId, string $code, string $name): void
    {
        if (!Schema::hasTable('erp_cost_centers')) {
            return;
        }
        $existing = DB::table('erp_cost_centers')
            ->where('tenant_id', $tenantId)->where('code', $code)->whereNull('deleted_at')->first();
        $row = $this->filterColumns('erp_cost_centers', [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'department_id' => $departmentId === 'n/a' ? null : $departmentId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        if ($existing) {
            unset($row['tenant_id']);
            DB::table('erp_cost_centers')->where('cost_center_id', $existing->cost_center_id)->update($row);

            return;
        }
        $row['cost_center_id'] = (string) Str::uuid();
        $row['created_at'] = now();
        $row['row_version'] = 1;
        $row = $this->filterColumns('erp_cost_centers', $row);
        DB::table('erp_cost_centers')->insert($row);
    }

    private function upsertHierarchy(string $tenantId, string $code, string $name, string $purpose): string
    {
        if (!Schema::hasTable('erp_org_hierarchies')) {
            return 'n/a';
        }
        $existing = DB::table('erp_org_hierarchies')
            ->where('tenant_id', $tenantId)->where('code', $code)->whereNull('deleted_at')->first();
        $row = $this->filterColumns('erp_org_hierarchies', [
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'purpose' => $purpose,
            'version' => 1,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        if ($existing) {
            unset($row['tenant_id']);
            DB::table('erp_org_hierarchies')->where('hierarchy_id', $existing->hierarchy_id)->update($row);

            return (string) $existing->hierarchy_id;
        }
        $id = (string) Str::uuid();
        $row['hierarchy_id'] = $id;
        $row['created_at'] = now();
        $row['row_version'] = 1;
        $row = $this->filterColumns('erp_org_hierarchies', $row);
        DB::table('erp_org_hierarchies')->insert($row);

        return $id;
    }

    private function upsertHierarchyNode(
        string $tenantId,
        string $hierarchyId,
        string $entityType,
        string $entityId,
        ?string $parentNodeId,
        int $sortOrder
    ): string {
        if (!Schema::hasTable('erp_org_hierarchy_nodes') || $hierarchyId === 'n/a' || $entityId === 'n/a') {
            return 'n/a';
        }
        $q = DB::table('erp_org_hierarchy_nodes')
            ->where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('deleted_at');
        $existing = $q->first();
        $row = $this->filterColumns('erp_org_hierarchy_nodes', [
            'tenant_id' => $tenantId,
            'hierarchy_id' => $hierarchyId,
            'parent_node_id' => $parentNodeId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        if ($existing) {
            unset($row['tenant_id']);
            DB::table('erp_org_hierarchy_nodes')->where('node_id', $existing->node_id)->update($row);

            return (string) $existing->node_id;
        }
        $id = (string) Str::uuid();
        $row['node_id'] = $id;
        $row['created_at'] = now();
        $row['row_version'] = 1;
        $row = $this->filterColumns('erp_org_hierarchy_nodes', $row);
        DB::table('erp_org_hierarchy_nodes')->insert($row);

        return $id;
    }

    private function upsertSalesOrg(string $tenantId, string $companyId, string $code, string $name): void
    {
        if (!Schema::hasTable('erp_sales_organizations')) {
            return;
        }
        $existing = DB::table('erp_sales_organizations')
            ->where('tenant_id', $tenantId)->where('code', $code)->whereNull('deleted_at')->first();
        $row = $this->filterColumns('erp_sales_organizations', [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        if ($existing) {
            unset($row['tenant_id']);
            DB::table('erp_sales_organizations')->where('sales_org_id', $existing->sales_org_id)->update($row);

            return;
        }
        $row['sales_org_id'] = (string) Str::uuid();
        $row['created_at'] = now();
        $row['row_version'] = 1;
        $row = $this->filterColumns('erp_sales_organizations', $row);
        DB::table('erp_sales_organizations')->insert($row);
    }

    private function upsertPurchOrg(string $tenantId, string $companyId, string $code, string $name): void
    {
        if (!Schema::hasTable('erp_purchasing_organizations')) {
            return;
        }
        $existing = DB::table('erp_purchasing_organizations')
            ->where('tenant_id', $tenantId)->where('code', $code)->whereNull('deleted_at')->first();
        $row = $this->filterColumns('erp_purchasing_organizations', [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        if ($existing) {
            unset($row['tenant_id']);
            DB::table('erp_purchasing_organizations')->where('purch_org_id', $existing->purch_org_id)->update($row);

            return;
        }
        $row['purch_org_id'] = (string) Str::uuid();
        $row['created_at'] = now();
        $row['row_version'] = 1;
        $row = $this->filterColumns('erp_purchasing_organizations', $row);
        DB::table('erp_purchasing_organizations')->insert($row);
    }

    private function upsertIcPartner(string $tenantId, string $fromCompanyId, string $toCompanyId): void
    {
        if (!Schema::hasTable('erp_ic_partners')) {
            return;
        }
        $exists = DB::table('erp_ic_partners')
            ->where('tenant_id', $tenantId)
            ->where('from_company_id', $fromCompanyId)
            ->where('to_company_id', $toCompanyId)
            ->whereNull('deleted_at')
            ->exists();
        if ($exists) {
            return;
        }
        $row = $this->filterColumns('erp_ic_partners', [
            'ic_partner_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'from_company_id' => $fromCompanyId,
            'to_company_id' => $toCompanyId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);
        DB::table('erp_ic_partners')->insert($row);
    }
}
