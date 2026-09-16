<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $systemTenantId = '00000000-0000-0000-0000-000000000001';

        DB::table('tenant_settings')->updateOrInsert(
            [
                'setting_key' => 'platform_config',
                'tenant_id'   => $systemTenantId,
            ],
            [
                'setting_value' => json_encode([
                    'platform_name'    => 'HamarehERP SaaS',
                    'default_currency' => 'IRR',
                    'multi_tenant'     => true,
                    'maintenance_mode' => false,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => 'platform_email_base_domain'],
                [
                    'system_setting_id' => (string) Str::uuid(),
                    'setting_value'     => 'erp.ir',
                    'description'       => 'Public platform base domain for organizational emails when tenant has no custom primary domain',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                    'row_version'       => 1,
                ]
            );
        }
    }
}
