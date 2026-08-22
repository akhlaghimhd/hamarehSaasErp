<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $systemTenantId = '00000000-0000-0000-0000-000000000001';

        DB::table('tenants')->updateOrInsert(
            ['tenant_id' => $systemTenantId],
            [
                'tenant_code' => 'system',
                'tenant_name' => 'System Root Tenant',
                'slug' => 'system',
                'tenant_type' => 1,
                'primary_domain_enabled' => false,
                'domain_status' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (Schema::hasTable('tenant_domains')) {
            DB::table('tenant_domains')->updateOrInsert(
                ['domain_name' => 'system.hamareherp.com'],
                [
                    'tenant_id'  => $systemTenantId,
                    'is_primary' => true,
                    'status'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $demoTenantId = '3ab77cac-1343-4b13-8e14-0d887aad132a';

        DB::table('tenants')->updateOrInsert(
            ['tenant_id' => $demoTenantId],
            [
                'tenant_code' => 'demo',
                'tenant_name' => 'Default Demo Tenant',
                'slug' => 'demo',
                'tenant_type' => 1,
                'primary_domain_enabled' => false,
                'domain_status' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (Schema::hasTable('tenant_domains')) {
            DB::table('tenant_domains')->updateOrInsert(
                ['domain_name' => 'demo.hamareherp.com'],
                [
                    'tenant_id'  => $demoTenantId,
                    'is_primary' => true,
                    'status'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}