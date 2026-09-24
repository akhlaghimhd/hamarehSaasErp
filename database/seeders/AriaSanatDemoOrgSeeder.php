<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sample multi-company group for local/QA: «گروه آریا صنعت».
 *
 * Idempotent on demo tenant (same resolution as DemoTenantOwnerSeeder).
 * Does NOT create users — run DemoTenantOwnerSeeder + PermissionSeeder first.
 *
 * Structure:
 *   HQ (ARYA-HQ) — primary OPERATING
 *   Subsidiary (ARYA-SUB) — OPERATING, parent = HQ, 80% equity ownership
 *   Branch under HQ, Business Unit, LEGAL hierarchy with both companies as nodes
 *
 * Usage:
 *   docker compose exec app php artisan db:seed --class=AriaSanatDemoOrgSeeder
 */
class AriaSanatDemoOrgSeeder extends Seeder
{
    private const DEMO_TENANT_ID = '3ab77cac-1343-4b13-8e14-0d887aad132a';

    public function run(): void
    {
        if (!Schema::hasTable('erp_companies')) {
            $this->command?->error('erp_companies missing.');

            return;
        }

        $tenantId = $this->resolveTenantId();
        if ($tenantId === null) {
            $this->command?->error('Demo tenant not found.');

            return;
        }

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        $hqId = $this->upsertCompany($tenantId, [
            'code'                 => 'ARYA-HQ',
            'name'                 => 'آریا صنعت',
            'legal_name'           => 'شرکت آریا صنعت (سهامی خاص)',
            'trade_name'           => 'آریا صنعت',
            'registration_number'  => '123456',
            'economic_code'        => '41111111111',
            'tax_identifier'       => '14001234567',
            'entity_kind'          => 'OPERATING',
            'is_primary'           => true,
            'parent_company_id'    => null,
            'is_active'            => true,
            'status'               => 1,
        ]);

        $subId = $this->upsertCompany($tenantId, [
            'code'                 => 'ARYA-SUB',
            'name'                 => 'آریا پخش',
            'legal_name'           => 'شرکت آریا پخش',
            'trade_name'           => 'آریا پخش',
            'registration_number'  => '654321',
            'economic_code'        => '42222222222',
            'tax_identifier'       => '14007654321',
            'entity_kind'          => 'OPERATING',
            'is_primary'           => false,
            'parent_company_id'    => $hqId,
            'is_active'            => true,
            'status'               => 1,
        ]);

        // Only one primary
        if (Schema::hasColumn('erp_companies', 'is_primary')) {
            DB::table('erp_companies')
                ->where('tenant_id', $tenantId)
                ->where('company_id', '!=', $hqId)
                ->whereNull('deleted_at')
                ->update(['is_primary' => false]);
        }

        $this->upsertOwnership($tenantId, $subId, $hqId, 80.0);
        $branchId = $this->upsertBranch($tenantId, $hqId);
        $buId = $this->upsertBusinessUnit($tenantId);
        $this->assignCompanyToBu($tenantId, $buId, $hqId);
        $hierId = $this->upsertLegalHierarchy($tenantId);
        $this->upsertHierarchyNode($tenantId, $hierId, 'COMPANY', $hqId, null, 10);
        $this->upsertHierarchyNode($tenantId, $hierId, 'COMPANY', $subId, null, 20);

        $this->command?->info('Aria Sanat demo org ready on tenant '.$tenantId);
        $this->command?->info('  HQ:     ARYA-HQ  ('.$hqId.')');
        $this->command?->info('  Sub:    ARYA-SUB ('.$subId.') parent=HQ');
        $this->command?->info('  Branch: '.$branchId);
        $this->command?->info('  BU:     '.$buId);
        $this->command?->info('  Hier:   '.$hierId);
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

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function upsertCompany(string $tenantId, array $attrs): string
    {
        $existing = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->where('code', $attrs['code'])
            ->whereNull('deleted_at')
            ->first();

        $row = array_merge($attrs, [
            'tenant_id'  => $tenantId,
            'updated_at' => now(),
        ]);

        // Drop columns that may not exist yet
        foreach (array_keys($row) as $col) {
            if ($col === 'tenant_id') {
                continue;
            }
            if (!Schema::hasColumn('erp_companies', $col)) {
                unset($row[$col]);
            }
        }

        if ($existing) {
            DB::table('erp_companies')->where('company_id', $existing->company_id)->update($row);

            return (string) $existing->company_id;
        }

        $id = (string) Str::uuid();
        $row['company_id'] = $id;
        $row['created_at'] = now();
        $row['row_version'] = 1;
        DB::table('erp_companies')->insert($row);

        return $id;
    }

    private function upsertOwnership(
        string $tenantId,
        string $companyId,
        string $ownerCompanyId,
        float $percent
    ): void {
        if (!Schema::hasTable('erp_company_ownerships')) {
            return;
        }

        $existing = DB::table('erp_company_ownerships')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('owner_company_id', $ownerCompanyId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            DB::table('erp_company_ownerships')->where('ownership_id', $existing->ownership_id)->update([
                'ownership_percent' => $percent,
                'relation_type'     => 'EQUITY',
                'status'            => 1,
                'updated_at'        => now(),
            ]);

            return;
        }

        DB::table('erp_company_ownerships')->insert([
            'ownership_id'      => (string) Str::uuid(),
            'tenant_id'         => $tenantId,
            'company_id'        => $companyId,
            'owner_company_id'  => $ownerCompanyId,
            'ownership_percent' => $percent,
            'relation_type'     => 'EQUITY',
            'status'            => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
            'row_version'       => 1,
        ]);
    }

    private function upsertBranch(string $tenantId, string $companyId): string
    {
        if (!Schema::hasTable('erp_branches')) {
            return 'n/a';
        }

        $code = 'ARYA-HQ-BR1';
        $existing = DB::table('erp_branches')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->first();

        $row = [
            'tenant_id'  => $tenantId,
            'company_id' => $companyId,
            'code'       => $code,
            'name'       => 'دفتر مرکزی تهران',
            'is_active'  => true,
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('erp_branches', 'branch_kind')) {
            $row['branch_kind'] = 'OFFICE';
        }

        if ($existing) {
            DB::table('erp_branches')->where('branch_id', $existing->branch_id)->update($row);

            return (string) $existing->branch_id;
        }

        $id = (string) Str::uuid();
        $row['branch_id'] = $id;
        $row['created_at'] = now();
        $row['row_version'] = 1;
        DB::table('erp_branches')->insert($row);

        return $id;
    }

    private function upsertBusinessUnit(string $tenantId): string
    {
        if (!Schema::hasTable('erp_business_units')) {
            return 'n/a';
        }

        $code = 'ARYA-BU-OPS';
        $existing = DB::table('erp_business_units')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->first();

        $row = [
            'tenant_id'  => $tenantId,
            'code'       => $code,
            'name'       => 'عملیات تولید و پخش',
            'is_active'  => true,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('erp_business_units')->where('business_unit_id', $existing->business_unit_id)->update($row);

            return (string) $existing->business_unit_id;
        }

        $id = (string) Str::uuid();
        $row['business_unit_id'] = $id;
        $row['created_at'] = now();
        $row['row_version'] = 1;
        DB::table('erp_business_units')->insert($row);

        return $id;
    }

    private function assignCompanyToBu(string $tenantId, string $buId, string $companyId): void
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
            return;
        }

        $row = [
            'tenant_id'        => $tenantId,
            'business_unit_id' => $buId,
            'company_id'       => $companyId,
            'created_at'       => now(),
            'updated_at'       => now(),
            'row_version'      => 1,
        ];
        if (Schema::hasColumn('erp_business_unit_companies', 'bu_company_id')) {
            $row['bu_company_id'] = (string) Str::uuid();
        } elseif (Schema::hasColumn('erp_business_unit_companies', 'id')) {
            $row['id'] = (string) Str::uuid();
        }
        if (Schema::hasColumn('erp_business_unit_companies', 'is_primary')) {
            $row['is_primary'] = true;
        }

        DB::table('erp_business_unit_companies')->insert($row);
    }

    private function upsertLegalHierarchy(string $tenantId): string
    {
        if (!Schema::hasTable('erp_org_hierarchies')) {
            return 'n/a';
        }

        $code = 'ARYA-LEGAL';
        $existing = DB::table('erp_org_hierarchies')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->first();

        $row = [
            'tenant_id'  => $tenantId,
            'code'       => $code,
            'name'       => 'ساختار حقوقی آریا صنعت',
            'purpose'    => 'LEGAL',
            'is_active'  => true,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('erp_org_hierarchies')->where('hierarchy_id', $existing->hierarchy_id)->update($row);

            return (string) $existing->hierarchy_id;
        }

        $id = (string) Str::uuid();
        $row['hierarchy_id'] = $id;
        $row['created_at'] = now();
        $row['row_version'] = 1;
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
    ): void {
        if (!Schema::hasTable('erp_org_hierarchy_nodes') || $hierarchyId === 'n/a') {
            return;
        }

        $existing = DB::table('erp_org_hierarchy_nodes')
            ->where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return;
        }

        DB::table('erp_org_hierarchy_nodes')->insert([
            'node_id'         => (string) Str::uuid(),
            'tenant_id'       => $tenantId,
            'hierarchy_id'    => $hierarchyId,
            'parent_node_id'  => $parentNodeId,
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'sort_order'      => $sortOrder,
            'created_at'      => now(),
            'updated_at'      => now(),
            'row_version'     => 1,
        ]);
    }
}
