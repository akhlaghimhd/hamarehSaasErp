<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * FIN-P0-17 — finance.* permission codes for all tenants (idempotent).
 */
class FinancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('tenant_permissions') || ! Schema::hasTable('tenants')) {
            return;
        }

        $perms = [
            ['code' => 'finance.coa.view', 'name' => 'مشاهده کدینگ حساب‌ها', 'action_type' => 'READ'],
            ['code' => 'finance.coa.create', 'name' => 'ایجاد حساب', 'action_type' => 'CREATE'],
            ['code' => 'finance.coa.update', 'name' => 'ویرایش حساب', 'action_type' => 'UPDATE'],
            ['code' => 'finance.coa.delete', 'name' => 'حذف نرم حساب', 'action_type' => 'DELETE'],
            ['code' => 'finance.journal.view', 'name' => 'مشاهده اسناد حسابداری', 'action_type' => 'READ'],
            ['code' => 'finance.journal.create', 'name' => 'ایجاد پیش‌نویس سند', 'action_type' => 'CREATE'],
            ['code' => 'finance.journal.update', 'name' => 'ویرایش پیش‌نویس سند', 'action_type' => 'UPDATE'],
            ['code' => 'finance.journal.delete', 'name' => 'حذف پیش‌نویس سند', 'action_type' => 'DELETE'],
            ['code' => 'finance.journal.post', 'name' => 'ثبت قطعی سند', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.journal.reverse', 'name' => 'برگشت سند', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.period.view', 'name' => 'مشاهده وضعیت دوره', 'action_type' => 'READ'],
            ['code' => 'finance.period.close', 'name' => 'بستن دوره مالی', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.period.reopen', 'name' => 'بازگشایی دوره نیمه‌بسته', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.report.view', 'name' => 'مشاهده گزارش‌های مالی', 'action_type' => 'READ'],
        ];

        $tenantIds = DB::table('tenants')->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();

        foreach ($tenantIds as $tenantId) {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

            foreach ($perms as $perm) {
                $exists = DB::table('tenant_permissions')
                    ->where('tenant_id', $tenantId)
                    ->where('code', $perm['code'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('tenant_permissions')->insert([
                    'tenant_permission_id' => (string) Str::uuid(),
                    'tenant_id'            => $tenantId,
                    'code'                 => $perm['code'],
                    'name'                 => $perm['name'],
                    'module_name'          => 'حسابداری مالی',
                    'action_type'          => $perm['action_type'],
                    'description'          => $perm['name'],
                    'status'               => 1,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        }
    }
}
