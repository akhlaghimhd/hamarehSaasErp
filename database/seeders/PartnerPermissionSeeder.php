<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * L3-P3-01 – Seeds PartnerLayer (Layer 3) permission codes into tenant_permissions
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
        $module = 'لایه شریک';

        return [
            ['code' => 'partner.partner.view', 'name' => 'مشاهده شرکا', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'فهرست و جزئیات شرکای تجاری را می‌بیند.'],
            ['code' => 'partner.partner.create', 'name' => 'ایجاد شریک', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'شریک تجاری جدید ثبت می‌کند.'],
            ['code' => 'partner.partner.update', 'name' => 'ویرایش شریک', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'اطلاعات شریک را ویرایش می‌کند.'],
            ['code' => 'partner.partner.delete', 'name' => 'حذف شریک', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'شریک را حذف نرم می‌کند.'],
            ['code' => 'partner.partner_user.view', 'name' => 'مشاهده کاربران شریک', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'کاربران وابسته به شریک را می‌بیند.'],
            ['code' => 'partner.partner_user.create', 'name' => 'ایجاد کاربر شریک', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'کاربر جدید برای شریک ثبت می‌کند.'],
            ['code' => 'partner.partner_user.update', 'name' => 'ویرایش کاربر شریک', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'کاربر شریک را ویرایش می‌کند.'],
            ['code' => 'partner.partner_user.delete', 'name' => 'حذف کاربر شریک', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'کاربر شریک را حذف می‌کند.'],
            ['code' => 'partner.assignment.view', 'name' => 'مشاهده تخصیص مستأجر به شریک', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'تخصیص سازمان‌ها به شرکا را می‌بیند.'],
            ['code' => 'partner.assignment.create', 'name' => 'ایجاد تخصیص مستأجر', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'سازمان را به شریک تخصیص می‌دهد.'],
            ['code' => 'partner.assignment.update', 'name' => 'ویرایش تخصیص مستأجر', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'تخصیص سازمان به شریک را ویرایش می‌کند.'],
            ['code' => 'partner.assignment.delete', 'name' => 'حذف تخصیص مستأجر', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'تخصیص سازمان به شریک را حذف می‌کند.'],
            ['code' => 'partner.agreement.view', 'name' => 'مشاهده توافق‌نامه‌های شریک', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'توافق‌نامه‌های شریک را می‌بیند.'],
            ['code' => 'partner.agreement.create', 'name' => 'ایجاد توافق‌نامه شریک', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'توافق‌نامه جدید برای شریک ثبت می‌کند.'],
            ['code' => 'partner.agreement.update', 'name' => 'ویرایش توافق‌نامه شریک', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'توافق‌نامه شریک را ویرایش می‌کند.'],
            ['code' => 'partner.agreement.delete', 'name' => 'حذف توافق‌نامه شریک', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'توافق‌نامه شریک را حذف می‌کند.'],
            ['code' => 'partner.commission_rule.view', 'name' => 'مشاهده قواعد کمیسیون', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'قواعد کمیسیون شریک را می‌بیند.'],
            ['code' => 'partner.commission_rule.create', 'name' => 'ایجاد قاعده کمیسیون', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'قاعده کمیسیون جدید تعریف می‌کند.'],
            ['code' => 'partner.commission_rule.update', 'name' => 'ویرایش قاعده کمیسیون', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'قاعده کمیسیون را ویرایش می‌کند.'],
            ['code' => 'partner.commission_rule.delete', 'name' => 'حذف قاعده کمیسیون', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'قاعده کمیسیون را حذف می‌کند.'],
            ['code' => 'partner.commission.view', 'name' => 'مشاهده کمیسیون‌ها', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'کمیسیون‌های محاسبه‌شده را می‌بیند.'],
            ['code' => 'partner.commission.create', 'name' => 'ایجاد کمیسیون', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'رکورد کمیسیون جدید ثبت می‌کند.'],
            ['code' => 'partner.commission.update', 'name' => 'ویرایش کمیسیون', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'کمیسیون را ویرایش می‌کند.'],
            ['code' => 'partner.commission.delete', 'name' => 'حذف کمیسیون', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'کمیسیون را حذف می‌کند.'],
            ['code' => 'partner.payout.view', 'name' => 'مشاهده پرداخت‌های شریک', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'پرداخت‌های شریک را می‌بیند.'],
            ['code' => 'partner.payout.create', 'name' => 'ایجاد پرداخت شریک', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'پرداخت به شریک ثبت می‌کند.'],
            ['code' => 'partner.payout.update', 'name' => 'ویرایش پرداخت شریک', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'پرداخت شریک را ویرایش می‌کند.'],
            ['code' => 'partner.payout.delete', 'name' => 'حذف پرداخت شریک', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'پرداخت شریک را حذف می‌کند.'],
            ['code' => 'partner.contact.view', 'name' => 'مشاهده مخاطبین شریک', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'مخاطبین شریک را می‌بیند.'],
            ['code' => 'partner.contact.create', 'name' => 'ایجاد مخاطب شریک', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'مخاطب جدید برای شریک ثبت می‌کند.'],
            ['code' => 'partner.contact.update', 'name' => 'ویرایش مخاطب شریک', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'مخاطب شریک را ویرایش می‌کند.'],
            ['code' => 'partner.contact.delete', 'name' => 'حذف مخاطب شریک', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'مخاطب شریک را حذف می‌کند.'],
            ['code' => 'partner.document.view', 'name' => 'مشاهده اسناد شریک', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'اسناد پیوست شریک را می‌بیند.'],
            ['code' => 'partner.document.create', 'name' => 'ایجاد سند شریک', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'سند جدید برای شریک ثبت می‌کند.'],
            ['code' => 'partner.document.update', 'name' => 'ویرایش سند شریک', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'سند شریک را ویرایش می‌کند.'],
            ['code' => 'partner.document.delete', 'name' => 'حذف سند شریک', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'سند شریک را حذف می‌کند.'],
            ['code' => 'partner.bank_account.view', 'name' => 'مشاهده حساب‌های بانکی شریک', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'حساب‌های بانکی شریک را می‌بیند.'],
            ['code' => 'partner.bank_account.create', 'name' => 'ایجاد حساب بانکی شریک', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'حساب بانکی جدید برای شریک ثبت می‌کند.'],
            ['code' => 'partner.bank_account.update', 'name' => 'ویرایش حساب بانکی شریک', 'module_name' => $module, 'action_type' => 'UPDATE', 'description' => 'حساب بانکی شریک را ویرایش می‌کند.'],
            ['code' => 'partner.bank_account.delete', 'name' => 'حذف حساب بانکی شریک', 'module_name' => $module, 'action_type' => 'DELETE', 'description' => 'حساب بانکی شریک را حذف می‌کند.'],
            ['code' => 'partner.activity_log.view', 'name' => 'مشاهده لاگ فعالیت شریک', 'module_name' => $module, 'action_type' => 'READ', 'description' => 'لاگ فعالیت‌های شریک را می‌بیند.'],
            ['code' => 'partner.activity_log.create', 'name' => 'ایجاد لاگ فعالیت شریک', 'module_name' => $module, 'action_type' => 'CREATE', 'description' => 'رویداد فعالیت شریک را ثبت می‌کند.'],
        ];
    }
}
