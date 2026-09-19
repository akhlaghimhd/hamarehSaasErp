<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * L3-P3-01 – Seeds PartnerLayer permission codes into tenant_permissions
 * for the demo tenant so route middleware permission:partner.* works.
 */
class PartnerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $demoTenantId = '3ab77cac-1343-4b13-8e14-0d887aad132a';

        $permissions = $this->getPartnerPermissions();

        $permissionIds = [];

        foreach ($permissions as $perm) {
            $existing = DB::table('tenant_permissions')
                ->where('tenant_id', $demoTenantId)
                ->where('code', $perm['code'])
                ->first();

            if ($existing) {
                DB::table('tenant_permissions')
                    ->where('tenant_permission_id', $existing->tenant_permission_id)
                    ->update([
                        'name'        => $perm['name'],
                        'module_name' => $perm['module_name'],
                        'action_type' => $perm['action_type'] ?? null,
                        'description' => $perm['description'] ?? null,
                        'status'      => 1,
                        'updated_at'  => now(),
                    ]);
                $permissionIds[] = $existing->tenant_permission_id;
            } else {
                $permissionId = (string) Str::uuid();
                DB::table('tenant_permissions')->insert([
                    'tenant_permission_id' => $permissionId,
                    'tenant_id'            => $demoTenantId,
                    'code'                 => $perm['code'],
                    'name'                 => $perm['name'],
                    'module_name'          => $perm['module_name'],
                    'action_type'          => $perm['action_type'] ?? null,
                    'description'          => $perm['description'] ?? null,
                    'status'               => 1,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                $permissionIds[] = $permissionId;
            }
        }

        $existingRole = DB::table('tenant_roles')
            ->where('tenant_id', $demoTenantId)
            ->where('code', 'tenant-admin')
            ->first();

        if ($existingRole && !empty($permissionIds)) {
            $actualRoleId = $existingRole->tenant_role_id;
            $insertData = [];
            foreach ($permissionIds as $permId) {
                $exists = DB::table('tenant_role_permissions')
                    ->where('tenant_role_id', $actualRoleId)
                    ->where('tenant_permission_id', $permId)
                    ->exists();
                if (!$exists) {
                    $insertData[] = [
                        'tenant_role_permission_id' => (string) Str::uuid(),
                        'tenant_id'                 => $demoTenantId,
                        'tenant_role_id'            => $actualRoleId,
                        'tenant_permission_id'      => $permId,
                        'created_at'                => now(),
                        'updated_at'                => now(),
                    ];
                }
            }
            if (!empty($insertData)) {
                DB::table('tenant_role_permissions')->insert($insertData);
            }
        }
    }

    private function getPartnerPermissions(): array
    {
        $module = 'شرکای تجاری';

        return [
            ['code' => 'partner.partner.view', 'name' => 'مشاهده طرف‌های تجاری', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'فهرست و جزئیات طرف‌های تجاری را می‌بیند.'],
            ['code' => 'partner.partner.create', 'name' => 'ایجاد طرف تجاری', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'طرف تجاری جدید ثبت می‌کند.'],
            ['code' => 'partner.partner.update', 'name' => 'ویرایش طرف تجاری', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'اطلاعات طرف تجاری را ویرایش می‌کند.'],
            ['code' => 'partner.partner.delete', 'name' => 'حذف طرف تجاری', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'طرف تجاری را حذف نرم می‌کند.'],
            ['code' => 'partner.partner_user.view', 'name' => 'مشاهده کاربران طرف تجاری', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'کاربران وابسته به طرف تجاری را می‌بیند.'],
            ['code' => 'partner.partner_user.create', 'name' => 'ایجاد کاربر طرف تجاری', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'کاربر جدید برای طرف تجاری ثبت می‌کند.'],
            ['code' => 'partner.partner_user.update', 'name' => 'ویرایش کاربر طرف تجاری', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'کاربر طرف تجاری را ویرایش می‌کند.'],
            ['code' => 'partner.partner_user.delete', 'name' => 'حذف کاربر طرف تجاری', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'کاربر طرف تجاری را حذف می‌کند.'],
            ['code' => 'partner.assignment.view', 'name' => 'مشاهده تخصیص سازمان', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'تخصیص سازمان‌ها به طرف‌های تجاری را می‌بیند.'],
            ['code' => 'partner.assignment.create', 'name' => 'ایجاد تخصیص سازمان', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'سازمان را به طرف تجاری تخصیص می‌دهد.'],
            ['code' => 'partner.assignment.update', 'name' => 'ویرایش تخصیص سازمان', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'تخصیص سازمان را ویرایش می‌کند.'],
            ['code' => 'partner.assignment.delete', 'name' => 'حذف تخصیص سازمان', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'تخصیص سازمان را حذف می‌کند.'],
            ['code' => 'partner.agreement.view', 'name' => 'مشاهده توافق‌نامه‌ها', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'توافق‌نامه‌ها را می‌بیند.'],
            ['code' => 'partner.agreement.create', 'name' => 'ایجاد توافق‌نامه', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'توافق‌نامه جدید ثبت می‌کند.'],
            ['code' => 'partner.agreement.update', 'name' => 'ویرایش توافق‌نامه', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'توافق‌نامه را ویرایش می‌کند.'],
            ['code' => 'partner.agreement.delete', 'name' => 'حذف توافق‌نامه', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'توافق‌نامه را حذف می‌کند.'],
            ['code' => 'partner.commission_rule.view', 'name' => 'مشاهده قواعد کمیسیون', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'قواعد کمیسیون را می‌بیند.'],
            ['code' => 'partner.commission_rule.create', 'name' => 'ایجاد قاعده کمیسیون', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'قاعده کمیسیون جدید تعریف می‌کند.'],
            ['code' => 'partner.commission_rule.update', 'name' => 'ویرایش قاعده کمیسیون', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'قاعده کمیسیون را ویرایش می‌کند.'],
            ['code' => 'partner.commission_rule.delete', 'name' => 'حذف قاعده کمیسیون', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'قاعده کمیسیون را حذف می‌کند.'],
            ['code' => 'partner.commission.view', 'name' => 'مشاهده کمیسیون‌ها', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'کمیسیون‌های محاسبه‌شده را می‌بیند.'],
            ['code' => 'partner.commission.create', 'name' => 'ایجاد کمیسیون', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'رکورد کمیسیون جدید ثبت می‌کند.'],
            ['code' => 'partner.commission.update', 'name' => 'ویرایش کمیسیون', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'کمیسیون را ویرایش می‌کند.'],
            ['code' => 'partner.commission.delete', 'name' => 'حذف کمیسیون', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'کمیسیون را حذف می‌کند.'],
            ['code' => 'partner.payout.view', 'name' => 'مشاهده تسویه‌ها', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'تسویه‌های طرف تجاری را می‌بیند.'],
            ['code' => 'partner.payout.create', 'name' => 'ایجاد تسویه', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'تسویه با طرف تجاری ثبت می‌کند.'],
            ['code' => 'partner.payout.update', 'name' => 'ویرایش تسویه', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'تسویه را ویرایش می‌کند.'],
            ['code' => 'partner.payout.delete', 'name' => 'حذف تسویه', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'تسویه را حذف می‌کند.'],
            ['code' => 'partner.contact.view', 'name' => 'مشاهده مخاطبین', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'مخاطبین طرف تجاری را می‌بیند.'],
            ['code' => 'partner.contact.create', 'name' => 'ایجاد مخاطب', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'مخاطب جدید ثبت می‌کند.'],
            ['code' => 'partner.contact.update', 'name' => 'ویرایش مخاطب', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'مخاطب را ویرایش می‌کند.'],
            ['code' => 'partner.contact.delete', 'name' => 'حذف مخاطب', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'مخاطب را حذف می‌کند.'],
            ['code' => 'partner.document.view', 'name' => 'مشاهده اسناد', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'اسناد پیوست را می‌بیند.'],
            ['code' => 'partner.document.create', 'name' => 'ایجاد سند', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'سند جدید ثبت می‌کند.'],
            ['code' => 'partner.document.update', 'name' => 'ویرایش سند', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'سند را ویرایش می‌کند.'],
            ['code' => 'partner.document.delete', 'name' => 'حذف سند', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'سند را حذف می‌کند.'],
            ['code' => 'partner.bank_account.view', 'name' => 'مشاهده حساب‌های بانکی', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'حساب‌های بانکی را می‌بیند.'],
            ['code' => 'partner.bank_account.create', 'name' => 'ایجاد حساب بانکی', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'حساب بانکی جدید ثبت می‌کند.'],
            ['code' => 'partner.bank_account.update', 'name' => 'ویرایش حساب بانکی', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'حساب بانکی را ویرایش می‌کند.'],
            ['code' => 'partner.bank_account.delete', 'name' => 'حذف حساب بانکی', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'حساب بانکی را حذف می‌کند.'],
            ['code' => 'partner.activity_log.view', 'name' => 'مشاهده لاگ فعالیت', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'لاگ فعالیت‌ها را می‌بیند.'],
            ['code' => 'partner.activity_log.create', 'name' => 'ایجاد لاگ فعالیت', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'رویداد فعالیت را ثبت می‌کند.'],
        ];
    }
}
