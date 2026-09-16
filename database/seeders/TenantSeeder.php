<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        // Default org email suffix for demo (shared-platform mode)
        if (Schema::hasTable('tenant_settings')) {
            foreach ([
                [$demoTenantId, 'demo'],
                [$systemTenantId, 'system'],
            ] as [$tid, $suffix]) {
                $exists = DB::table('tenant_settings')
                    ->where('tenant_id', $tid)
                    ->where('setting_key', 'email_domain_suffix')
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$exists) {
                    DB::table('tenant_settings')->insert([
                        'tenant_setting_id' => (string) Str::uuid(),
                        'tenant_id'         => $tid,
                        'setting_key'       => 'email_domain_suffix',
                        'setting_value'     => $suffix,
                        'setting_group'     => 'IDENTITY',
                        'created_at'        => now(),
                        'updated_at'        => now(),
                        'row_version'       => 1,
                    ]);
                }
            }
        }
    }
}
