<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $demoTenantId = '3ab77cac-1343-4b13-8e14-0d887aad132a';

        $permissions = $this->getBasePermissions();

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

        if ($existingRole) {
            $actualRoleId = $existingRole->tenant_role_id;

            DB::table('tenant_roles')
                ->where('tenant_role_id', $actualRoleId)
                ->update([
                    'name'        => 'مدیر سازمان',
                    'description' => 'دسترسی کامل به تمام قابلیت‌های سازمان',
                    'status'      => 1,
                    'updated_at'  => now(),
                ]);
        } else {
            $actualRoleId = (string) Str::uuid();

            DB::table('tenant_roles')->insert([
                'tenant_role_id' => $actualRoleId,
                'tenant_id'      => $demoTenantId,
                'code'           => 'tenant-admin',
                'name'           => 'مدیر سازمان',
                'description'    => 'دسترسی کامل به تمام قابلیت‌های سازمان',
                'status'         => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        DB::table('tenant_role_permissions')
            ->where('tenant_id', $demoTenantId)
            ->where('tenant_role_id', $actualRoleId)
            ->delete();

        $insertData = [];
        foreach ($permissionIds as $permId) {
            $insertData[] = [
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $demoTenantId,
                'tenant_role_id'            => $actualRoleId,
                'tenant_permission_id'      => $permId,
                'created_at'                => now(),
                'updated_at'                => now(),
            ];
        }

        if (!empty($insertData)) {
            DB::table('tenant_role_permissions')->insert($insertData);
        }

        $this->assignTenantAdminToDemoMembers($demoTenantId, $actualRoleId);

        $this->seedDemoRoles($demoTenantId);
    }

    private function assignTenantAdminToDemoMembers(string $tenantId, string $roleId): void
    {
        if (!Schema::hasTable('tenant_users') || !Schema::hasTable('tenant_user_roles')) {
            return;
        }

        $query = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereNull('deleted_at');

        $owners = (clone $query)->where('is_owner', true)->get(['user_id']);
        $targets = $owners->isNotEmpty()
            ? $owners
            : $query->get(['user_id']);

        foreach ($targets as $row) {
            $exists = DB::table('tenant_user_roles')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $row->user_id)
                ->where('tenant_role_id', $roleId)
                ->exists();

            if ($exists) {
                continue;
            }

            $payload = [
                'tenant_user_role_id' => (string) Str::uuid(),
                'tenant_id'           => $tenantId,
                'user_id'             => $row->user_id,
                'tenant_role_id'      => $roleId,
                'created_at'          => now(),
                'updated_at'          => now(),
            ];

            DB::table('tenant_user_roles')->insert($payload);
        }
    }

    private function seedDemoRoles(string $tenantId): void
    {
        $demoRoles = [
            [
                'code' => 'accountant',
                'name' => 'حسابدار',
                'description' => 'دسترسی به اسناد و حساب‌ها',
                'permission_codes' => [
                    'accounting.voucher.view',
                    'accounting.voucher.post',
                    'accounting.account.view',
                    'identity.user.view',
                    'identity.profile.view',
                ],
            ],
            [
                'code' => 'sales',
                'name' => 'فروش',
                'description' => 'سفارش و فاکتور فروش',
                'permission_codes' => [
                    'procurement.sales-order.create',
                    'procurement.sales-order.view',
                    'procurement.sales-order.confirm',
                    'procurement.sales-invoice.view',
                    'procurement.sales-invoice.create',
                    'identity.user.view',
                    'identity.profile.view',
                ],
            ],
            [
                'code' => 'warehouse',
                'name' => 'انباردار',
                'description' => 'کالا، انبار و اسناد موجودی',
                'permission_codes' => [
                    'inventory.item.view',
                    'inventory.item.create',
                    'inventory.warehouse.view',
                    'inventory.document.view',
                    'inventory.document.create',
                    'inventory.document.post',
                    'identity.user.view',
                    'identity.profile.view',
                ],
            ],
            [
                'code' => 'hr-viewer',
                'name' => 'مشاهده‌گر منابع انسانی',
                'description' => 'فقط مشاهده کاربران و تاریخچه',
                'permission_codes' => [
                    'identity.user.view',
                    'identity.profile.view',
                    'identity.membership_history.view',
                    'identity.role.view',
                ],
            ],
            [
                'code' => 'purchase',
                'name' => 'خرید',
                'description' => 'سفارش خرید و رسید',
                'permission_codes' => [
                    'procurement.purchase-order.create',
                    'procurement.purchase-receipt.create',
                    'procurement.purchase-receipt.view',
                    'procurement.purchase-receipt.post',
                    'procurement.purchase-requisition.view',
                    'procurement.purchase-requisition.create',
                    'identity.user.view',
                    'identity.profile.view',
                ],
            ],
        ];

        $codeToId = [];
        $rows = DB::table('tenant_permissions')
            ->where('tenant_id', $tenantId)
            ->get(['tenant_permission_id', 'code']);
        foreach ($rows as $row) {
            $codeToId[$row->code] = $row->tenant_permission_id;
        }

        foreach ($demoRoles as $roleDef) {
            $existing = DB::table('tenant_roles')
                ->where('tenant_id', $tenantId)
                ->where('code', $roleDef['code'])
                ->first();

            if ($existing) {
                $roleId = $existing->tenant_role_id;
                DB::table('tenant_roles')
                    ->where('tenant_role_id', $roleId)
                    ->update([
                        'name'        => $roleDef['name'],
                        'description' => $roleDef['description'],
                        'status'      => 1,
                        'updated_at'  => now(),
                    ]);
            } else {
                $roleId = (string) Str::uuid();
                DB::table('tenant_roles')->insert([
                    'tenant_role_id' => $roleId,
                    'tenant_id'      => $tenantId,
                    'code'           => $roleDef['code'],
                    'name'           => $roleDef['name'],
                    'description'    => $roleDef['description'],
                    'status'         => 1,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            DB::table('tenant_role_permissions')
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $roleId)
                ->delete();

            $insertData = [];
            foreach ($roleDef['permission_codes'] as $code) {
                if (!isset($codeToId[$code])) {
                    continue;
                }
                $insertData[] = [
                    'tenant_role_permission_id' => (string) Str::uuid(),
                    'tenant_id'                 => $tenantId,
                    'tenant_role_id'            => $roleId,
                    'tenant_permission_id'      => $codeToId[$code],
                    'created_at'                => now(),
                    'updated_at'                => now(),
                ];
            }
            if (!empty($insertData)) {
                DB::table('tenant_role_permissions')->insert($insertData);
            }
        }
    }

    private function getBasePermissions(): array
    {
        return [
            ['code' => 'identity.user.view', 'name' => 'مشاهده کاربران', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'فهرست و جزئیات اعضای سازمان را می‌بیند.'],
            ['code' => 'identity.user.create', 'name' => 'ایجاد کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'می‌تواند عضو جدید به سازمان اضافه کند.'],
            ['code' => 'identity.user.update', 'name' => 'ویرایش کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'اطلاعات و وضعیت اعضا را ویرایش می‌کند.'],
            ['code' => 'identity.user.delete', 'name' => 'حذف کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'حذف نرم عضویت کاربر از سازمان.'],
            ['code' => 'identity.user.restore', 'name' => 'بازگردانی کاربر حذف‌شده', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'اعضای حذف‌شده را دوباره فعال می‌کند.'],
            ['code' => 'identity.role.view', 'name' => 'مشاهده نقش‌ها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'فهرست و جزئیات نقش‌ها و سلسله‌مراتب را می‌بیند.'],
            ['code' => 'identity.role.create', 'name' => 'ایجاد نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'نقش جدید (ریشه یا زیرنقش) تعریف می‌کند.'],
            ['code' => 'identity.role.update', 'name' => 'ویرایش نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'نام، توضیح و وضعیت نقش را تغییر می‌دهد.'],
            ['code' => 'identity.role.delete', 'name' => 'حذف نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'حذف نرم نقش (در صورت عدم وابستگی بحرانی).'],
            ['code' => 'identity.role.assign', 'name' => 'تخصیص نقش به کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'نقش‌ها را به اعضای سازمان نسبت می‌دهد یا برمی‌دارد.'],
            ['code' => 'identity.role.assign-permissions', 'name' => 'تخصیص مجوز به نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'مجوزهای هر نقش را تنظیم می‌کند.'],
            ['code' => 'identity.role.manage', 'name' => 'مدیریت نقش‌ها (قدیمی)', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'مجوز یکپارچه قدیمی برای مدیریت نقش‌ها.'],
            ['code' => 'identity.permission.view', 'name' => 'مشاهده مجوزها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'فهرست مجوزهای تعریف‌شده سازمان را می‌بیند.'],
            ['code' => 'identity.permission.create', 'name' => 'ایجاد مجوز', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'مجوز جدید در کاتالوگ سازمان ثبت می‌کند.'],
            ['code' => 'identity.permission.update', 'name' => 'ویرایش مجوز', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'عنوان و جزئیات مجوز را ویرایش می‌کند.'],
            ['code' => 'identity.permission.delete', 'name' => 'حذف مجوز', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'مجوز را به‌صورت نرم از کاتالوگ حذف می‌کند.'],
            ['code' => 'identity.scope.view', 'name' => 'مشاهده محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'محدوده‌های دسترسی (شعبه، انبار و …) را می‌بیند.'],
            ['code' => 'identity.scope.create', 'name' => 'ایجاد محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'محدوده دسترسی جدید تعریف می‌کند.'],
            ['code' => 'identity.scope.update', 'name' => 'ویرایش محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'محدوده دسترسی موجود را ویرایش می‌کند.'],
            ['code' => 'identity.scope.delete', 'name' => 'حذف محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'محدوده دسترسی را حذف نرم می‌کند.'],
            ['code' => 'identity.scope.assign', 'name' => 'تخصیص محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'محدوده دسترسی را به کاربران نسبت می‌دهد.'],
            ['code' => 'identity.profile.view', 'name' => 'مشاهده پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'پروفایل کاربری خود یا دیگران (در حد مجاز) را می‌بیند.'],
            ['code' => 'identity.profile.update', 'name' => 'ویرایش پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'اطلاعات پروفایل را به‌روزرسانی می‌کند.'],
            ['code' => 'identity.profile.delete', 'name' => 'حذف پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'پروفایل را حذف نرم می‌کند.'],
            ['code' => 'identity.membership_history.view', 'name' => 'مشاهده تاریخچه عضویت', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'رویدادهای عضویت (پیوستن، تغییر وضعیت، حذف) را می‌بیند.'],
            ['code' => 'masterdata.business-partner.view', 'name' => 'مشاهده طرف‌های تجاری', 'module_name' => 'داده‌های پایه', 'action_type' => 'READ', 'description' => 'فهرست مشتریان و تأمین‌کنندگان را می‌بیند.'],
            ['code' => 'masterdata.business-partner.create', 'name' => 'ایجاد طرف تجاری', 'module_name' => 'داده‌های پایه', 'action_type' => 'CREATE', 'description' => 'طرف تجاری جدید ثبت می‌کند.'],
            ['code' => 'masterdata.business-partner.update', 'name' => 'ویرایش طرف تجاری', 'module_name' => 'داده‌های پایه', 'action_type' => 'UPDATE', 'description' => 'اطلاعات طرف تجاری را ویرایش می‌کند.'],
            ['code' => 'inventory.item.view', 'name' => 'مشاهده کالاها', 'module_name' => 'انبار', 'action_type' => 'READ', 'description' => 'کاتالوگ کالا و مشخصات را می‌بیند.'],
            ['code' => 'inventory.item.create', 'name' => 'ایجاد کالا', 'module_name' => 'انبار', 'action_type' => 'CREATE', 'description' => 'کالای جدید در سیستم ثبت می‌کند.'],
            ['code' => 'inventory.warehouse.view', 'name' => 'مشاهده انبارها', 'module_name' => 'انبار', 'action_type' => 'READ', 'description' => 'فهرست انبارها و وضعیت آن‌ها را می‌بیند.'],
            ['code' => 'inventory.document.view', 'name' => 'مشاهده اسناد موجودی', 'module_name' => 'انبار', 'action_type' => 'READ', 'description' => 'اسناد ورود/خروج و انتقال موجودی را می‌بیند.'],
            ['code' => 'inventory.document.create', 'name' => 'ایجاد سند موجودی', 'module_name' => 'انبار', 'action_type' => 'CREATE', 'description' => 'سند موجودی جدید صادر می‌کند.'],
            ['code' => 'inventory.document.post', 'name' => 'ثبت نهایی سند موجودی', 'module_name' => 'انبار', 'action_type' => 'EXECUTE', 'description' => 'سند موجودی را قطعی و مؤثر بر موجودی می‌کند.'],
            ['code' => 'accounting.voucher.view', 'name' => 'مشاهده اسناد حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'READ', 'description' => 'اسناد و ردیف‌های حسابداری را می‌بیند.'],
            ['code' => 'accounting.voucher.post', 'name' => 'ثبت نهایی سند حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'EXECUTE', 'description' => 'سند حسابداری را قطعی می‌کند.'],
            ['code' => 'accounting.account.view', 'name' => 'مشاهده حساب‌ها', 'module_name' => 'حسابداری', 'action_type' => 'READ', 'description' => 'نمودار حساب‌ها را مشاهده می‌کند.'],
            ['code' => 'procurement.purchase-order.create', 'name' => 'ایجاد سفارش خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'سفارش خرید جدید ثبت می‌کند.'],
            ['code' => 'procurement.purchase-receipt.create', 'name' => 'ایجاد رسید خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'رسید دریافت کالا از تأمین‌کننده ثبت می‌کند.'],
            ['code' => 'procurement.purchase-receipt.view', 'name' => 'مشاهده رسید خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'رسیدهای خرید را مشاهده می‌کند.'],
            ['code' => 'procurement.purchase-receipt.post', 'name' => 'ثبت نهایی رسید خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'رسید خرید را قطعی و مؤثر می‌کند.'],
            ['code' => 'procurement.sales-order.create', 'name' => 'ایجاد سفارش فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'سفارش فروش برای مشتری ثبت می‌کند.'],
            ['code' => 'procurement.sales-order.view', 'name' => 'مشاهده سفارش فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'سفارش‌های فروش را می‌بیند.'],
            ['code' => 'procurement.sales-order.confirm', 'name' => 'تأیید سفارش فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'سفارش فروش را تأیید می‌کند.'],
            ['code' => 'procurement.sales-delivery.create', 'name' => 'ایجاد حواله فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'حواله خروج کالا برای فروش ثبت می‌کند.'],
            ['code' => 'procurement.sales-delivery.view', 'name' => 'مشاهده حواله فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'حواله‌های فروش را مشاهده می‌کند.'],
            ['code' => 'procurement.sales-delivery.post', 'name' => 'ثبت نهایی حواله فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'حواله فروش را قطعی می‌کند.'],
            ['code' => 'procurement.sales-quotation.create', 'name' => 'ایجاد پیش‌فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'پیش‌فاکتور فروش صادر می‌کند.'],
            ['code' => 'procurement.return-order.create', 'name' => 'ایجاد سفارش مرجوعی', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'سفارش مرجوعی کالا ثبت می‌کند.'],
            ['code' => 'procurement.sales-invoice.view', 'name' => 'مشاهده فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'فاکتورهای فروش را می‌بیند.'],
            ['code' => 'procurement.sales-invoice.create', 'name' => 'ایجاد فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'فاکتور فروش صادر می‌کند.'],
            ['code' => 'procurement.sales-invoice.update', 'name' => 'ویرایش فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'UPDATE', 'description' => 'فاکتور فروش را قبل از قطعی شدن ویرایش می‌کند.'],
            ['code' => 'procurement.sales-invoice.post', 'name' => 'ثبت نهایی فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'فاکتور فروش را قطعی می‌کند.'],
            ['code' => 'procurement.purchase-invoice.view', 'name' => 'مشاهده فاکتور خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'فاکتورهای خرید را می‌بیند.'],
            ['code' => 'procurement.purchase-invoice.create', 'name' => 'ایجاد فاکتور خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'فاکتور خرید ثبت می‌کند.'],
            ['code' => 'procurement.purchase-invoice.post', 'name' => 'ثبت نهایی فاکتور خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'فاکتور خرید را قطعی می‌کند.'],
            ['code' => 'procurement.payment-schedule.view', 'name' => 'مشاهده برنامه پرداخت', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'برنامه‌های پرداخت و سررسیدها را می‌بیند.'],
            ['code' => 'procurement.cash-transaction.create', 'name' => 'ایجاد تراکنش نقدی', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'دریافت یا پرداخت نقدی ثبت می‌کند.'],
            ['code' => 'procurement.cash-transaction.view', 'name' => 'مشاهده تراکنش نقدی', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'تراکنش‌های نقدی را مشاهده می‌کند.'],
            ['code' => 'procurement.purchase-requisition.view', 'name' => 'مشاهده درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'درخواست‌های خرید داخلی را می‌بیند.'],
            ['code' => 'procurement.purchase-requisition.create', 'name' => 'ایجاد درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'درخواست خرید داخلی ثبت می‌کند.'],
            ['code' => 'procurement.purchase-requisition.submit', 'name' => 'ارسال درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'درخواست خرید را برای تأیید ارسال می‌کند.'],
            ['code' => 'procurement.purchase-requisition.approve', 'name' => 'تأیید درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'درخواست خرید را تأیید یا رد می‌کند.'],
            ['code' => 'workflow.definition.manage', 'name' => 'مدیریت تعریف گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'EXECUTE', 'description' => 'تعاریف و مراحل گردش‌کار را مدیریت می‌کند.'],
            ['code' => 'workflow.instance.start', 'name' => 'شروع نمونه گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'EXECUTE', 'description' => 'یک نمونه گردش‌کار جدید آغاز می‌کند.'],
            ['code' => 'workflow.instance.view', 'name' => 'مشاهده نمونه گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'READ', 'description' => 'وضعیت نمونه‌های در حال اجرا را می‌بیند.'],
            ['code' => 'workflow.task.view', 'name' => 'مشاهده کارتابل', 'module_name' => 'گردش کار', 'action_type' => 'READ', 'description' => 'وظایف محول‌شده در کارتابل را می‌بیند.'],
            ['code' => 'workflow.task.complete', 'name' => 'انجام وظیفه گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'EXECUTE', 'description' => 'وظیفه کارتابل را تکمیل و به مرحله بعد می‌برد.'],
        ];
    }
}
