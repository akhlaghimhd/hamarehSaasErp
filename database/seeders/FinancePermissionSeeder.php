<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * FIN-P0..P5 finance.* permission codes (idempotent).
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
            ['code' => 'finance.treasury.view', 'name' => 'مشاهده خزانه و چک و صورت‌حساب', 'action_type' => 'READ'],
            ['code' => 'finance.treasury.manage', 'name' => 'مدیریت اسناد خزانه و چک', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.treasury.post', 'name' => 'ثبت خزانه در دفتر کل', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.ar.view', 'name' => 'مشاهده دریافتنی / پرداختنی و عمر بدهی', 'action_type' => 'READ'],
            ['code' => 'finance.ar.manage', 'name' => 'ثبت و تسویه آیتم‌های باز AR/AP', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.ap.view', 'name' => 'مشاهده حساب‌های پرداختنی', 'action_type' => 'READ'],
            ['code' => 'finance.ap.manage', 'name' => 'مدیریت حساب‌های پرداختنی', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.tax.view', 'name' => 'مشاهده نرخ و تراکنش مالیاتی', 'action_type' => 'READ'],
            ['code' => 'finance.tax.manage', 'name' => 'مدیریت نرخ و ثبت تراکنش مالیاتی', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.moodian.view', 'name' => 'مشاهده ارسال مودیان', 'action_type' => 'READ'],
            ['code' => 'finance.moodian.submit', 'name' => 'ارسال به سامانه مودیان', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.compliance.view', 'name' => 'مشاهده هشدارهای انطباق', 'action_type' => 'READ'],
            ['code' => 'finance.compliance.manage', 'name' => 'اسکن و بستن هشدار انطباق', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.suggest.view', 'name' => 'مشاهده پیشنهاد اسناد هوشمند', 'action_type' => 'READ'],
            ['code' => 'finance.suggest.manage', 'name' => 'ایجاد پیشنهاد و قواعد تعیین حساب', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.suggest.decide', 'name' => 'قبول یا رد پیشنهاد سند', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.fa.view', 'name' => 'مشاهده دارایی ثابت', 'action_type' => 'READ'],
            ['code' => 'finance.fa.manage', 'name' => 'ثبت دارایی ثابت', 'action_type' => 'EXECUTE'],
            ['code' => 'finance.fa.depreciate', 'name' => 'اجرای استهلاک (پیش‌نویس)', 'action_type' => 'EXECUTE'],
            // P5
            ['code' => 'finance.ic.view', 'name' => 'مشاهده بین شرکتی و تراز تلفیقی', 'action_type' => 'READ'],
            ['code' => 'finance.ic.manage', 'name' => 'ثبت نقشه و پیش‌نویس IC / حذف', 'action_type' => 'EXECUTE'],
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
