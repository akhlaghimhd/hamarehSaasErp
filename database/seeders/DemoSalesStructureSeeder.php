<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * H2-05: Demo sales structure catalog (channels, divisions, areas, offices, groups).
 * Idempotent. Safe to run after AriaSanatDemoOrgSeeder (needs SO-DOM / SO-EXP).
 */
class DemoSalesStructureSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = $this->resolveTenantId();
        if (!$tenantId) {
            $this->command?->warn('DemoSalesStructureSeeder: no demo tenant found, skip.');

            return;
        }

        $chRetail = $this->upsertChannel($tenantId, 'RETAIL', 'خرده‌فروشی');
        $chWholesale = $this->upsertChannel($tenantId, 'WHOLESALE', 'عمده‌فروشی');
        $chExport = $this->upsertChannel($tenantId, 'EXPORT', 'صادرات');

        $divFg = $this->upsertDivision($tenantId, 'FG', 'کالای ساخته‌شده');
        $divSpare = $this->upsertDivision($tenantId, 'SPARE', 'قطعات یدکی');
        $divSvc = $this->upsertDivision($tenantId, 'SVC', 'خدمات');

        $soDom = DB::table('erp_sales_organizations')
            ->where('tenant_id', $tenantId)->where('code', 'SO-DOM')->whereNull('deleted_at')->value('sales_org_id');
        $soExp = DB::table('erp_sales_organizations')
            ->where('tenant_id', $tenantId)->where('code', 'SO-EXP')->whereNull('deleted_at')->value('sales_org_id');

        if ($soDom && $chRetail && $divFg) {
            $this->upsertArea($tenantId, $soDom, $chRetail, $divFg, 'SO-DOM-RETAIL-FG', 'فروش داخلی خرده‌فروشی');
        }
        if ($soDom && $chWholesale && $divFg) {
            $this->upsertArea($tenantId, $soDom, $chWholesale, $divFg, 'SO-DOM-WS-FG', 'فروش داخلی عمده');
        }
        if ($soExp && $chExport && $divFg) {
            $this->upsertArea($tenantId, $soExp, $chExport, $divFg, 'SO-EXP-EXP-FG', 'صادرات کالای ساخته');
        }
        if ($soDom && $chRetail && $divSpare) {
            $this->upsertArea($tenantId, $soDom, $chRetail, $divSpare, 'SO-DOM-RETAIL-SP', 'خرده‌فروشی قطعات');
        }

        if ($soDom) {
            $office = $this->upsertOffice($tenantId, 'TEH-01', 'دفتر تهران', $soDom);
            if ($office) {
                $this->upsertGroup($tenantId, $office, 'G-NORTH', 'تیم شمال');
                $this->upsertGroup($tenantId, $office, 'G-SOUTH', 'تیم جنوب');
            }
            $office2 = $this->upsertOffice($tenantId, 'ISF-01', 'دفتر اصفهان', $soDom);
            if ($office2) {
                $this->upsertGroup($tenantId, $office2, 'G-CENTRAL', 'تیم مرکزی');
            }
        }

        $this->command?->info('DemoSalesStructureSeeder done for tenant '.$tenantId);
    }

    private function resolveTenantId(): ?string
    {
        $fromEnv = env('DEMO_TENANT_ID');
        if (is_string($fromEnv) && $fromEnv !== '' && DB::table('tenants')->where('tenant_id', $fromEnv)->exists()) {
            return $fromEnv;
        }
        $row = DB::table('tenants')->orderBy('created_at')->first();

        return $row->tenant_id ?? null;
    }

    private function upsertChannel(string $tenantId, string $code, string $name): ?string
    {
        if (!Schema::hasTable('erp_distribution_channels')) {
            return null;
        }
        $existing = DB::table('erp_distribution_channels')
            ->where('tenant_id', $tenantId)->where('code', $code)->whereNull('deleted_at')->first();
        if ($existing) {
            return $existing->distribution_channel_id;
        }
        $id = (string) Str::uuid();
        DB::table('erp_distribution_channels')->insert([
            'distribution_channel_id' => $id,
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'row_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertDivision(string $tenantId, string $code, string $name): ?string
    {
        if (!Schema::hasTable('erp_product_divisions')) {
            return null;
        }
        $existing = DB::table('erp_product_divisions')
            ->where('tenant_id', $tenantId)->where('code', $code)->whereNull('deleted_at')->first();
        if ($existing) {
            return $existing->division_id;
        }
        $id = (string) Str::uuid();
        DB::table('erp_product_divisions')->insert([
            'division_id' => $id,
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'row_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertArea(
        string $tenantId,
        string $salesOrgId,
        string $channelId,
        string $divisionId,
        string $code,
        string $name
    ): void {
        if (!Schema::hasTable('erp_sales_areas')) {
            return;
        }
        $exists = DB::table('erp_sales_areas')
            ->where('tenant_id', $tenantId)
            ->where('sales_org_id', $salesOrgId)
            ->where('distribution_channel_id', $channelId)
            ->where('division_id', $divisionId)
            ->whereNull('deleted_at')
            ->exists();
        if ($exists) {
            return;
        }
        DB::table('erp_sales_areas')->insert([
            'sales_area_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'sales_org_id' => $salesOrgId,
            'distribution_channel_id' => $channelId,
            'division_id' => $divisionId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'row_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertOffice(string $tenantId, string $code, string $name, ?string $salesOrgId): ?string
    {
        if (!Schema::hasTable('erp_sales_offices')) {
            return null;
        }
        $existing = DB::table('erp_sales_offices')
            ->where('tenant_id', $tenantId)->where('code', $code)->whereNull('deleted_at')->first();
        if ($existing) {
            return $existing->sales_office_id;
        }
        $id = (string) Str::uuid();
        DB::table('erp_sales_offices')->insert([
            'sales_office_id' => $id,
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'sales_org_id' => $salesOrgId,
            'is_active' => true,
            'row_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertGroup(string $tenantId, string $officeId, string $code, string $name): void
    {
        if (!Schema::hasTable('erp_sales_groups')) {
            return;
        }
        $exists = DB::table('erp_sales_groups')
            ->where('tenant_id', $tenantId)
            ->where('sales_office_id', $officeId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->exists();
        if ($exists) {
            return;
        }
        DB::table('erp_sales_groups')->insert([
            'sales_group_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'sales_office_id' => $officeId,
            'is_active' => true,
            'row_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
