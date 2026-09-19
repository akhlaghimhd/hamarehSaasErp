<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot / re-runnable: map English module_name values on tenant_permissions to Persian.
 * Safe to run after other permission seeders.
 */
class LocalizePermissionModulesSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'Identity' => 'هویت و دسترسی',
            'Accounting' => 'حسابداری',
            'Inventory' => 'انبار',
            'MasterData' => 'داده‌های پایه',
            'Organization' => 'سازمان',
            'PartnerLayer' => 'لایه شریک',
            'ProcurementSales' => 'خرید و فروش',
            'SaasAdmin' => 'مدیریت پلتفرم',
            'SaasPlatform' => 'پلتفرم SaaS',
            'Workflow' => 'گردش کار',
            'DocumentManagement' => 'مدیریت اسناد',
            'Manufacturing' => 'تولید',
        ];

        foreach ($map as $en => $fa) {
            DB::table('tenant_permissions')
                ->where('module_name', $en)
                ->update([
                    'module_name' => $fa,
                    'updated_at' => now(),
                ]);
        }
    }
}
