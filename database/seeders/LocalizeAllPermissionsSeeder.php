<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Force Persian labels for ALL known tenant_permissions rows (by code).
 * Also maps leftover English module_name values and heuristic English names.
 * Safe to re-run.
 */
class LocalizeAllPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $perm) {
            DB::table('tenant_permissions')
                ->where('code', $perm['code'])
                ->update([
                    'name' => $perm['name'],
                    'module_name' => $perm['module_name'],
                    'action_type' => $perm['action_type'] ?? null,
                    'description' => $perm['description'] ?? null,
                    'updated_at' => now(),
                ]);
        }

        $moduleMap = [
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
        foreach ($moduleMap as $en => $fa) {
            DB::table('tenant_permissions')
                ->where('module_name', $en)
                ->update(['module_name' => $fa, 'updated_at' => now()]);
        }

        $rows = DB::table('tenant_permissions')
            ->whereRaw("name ~ '[A-Za-z]{3,}'")
            ->whereRaw("name !~ '[\\x{0600}-\\x{06FF}]'")
            ->get(['tenant_permission_id', 'code', 'name']);

        foreach ($rows as $row) {
            $fa = $this->heuristicFaName($row->code, $row->name);
            if ($fa !== $row->name) {
                DB::table('tenant_permissions')
                    ->where('tenant_permission_id', $row->tenant_permission_id)
                    ->update(['name' => $fa, 'updated_at' => now()]);
            }
        }
    }

    private function heuristicFaName(string $code, string $name): string
    {
        $parts = explode('.', $code);
        $action = end($parts);
        $actionFa = [
            'view' => 'مشاهده', 'create' => 'ایجاد', 'update' => 'ویرایش', 'delete' => 'حذف',
            'restore' => 'بازگردانی', 'post' => 'ثبت نهایی', 'confirm' => 'تأیید', 'approve' => 'تأیید',
            'submit' => 'ارسال', 'assign' => 'تخصیص', 'manage' => 'مدیریت', 'start' => 'شروع', 'complete' => 'انجام',
        ][$action] ?? null;

        $entity = $parts[count($parts) > 2 ? count($parts) - 2 : 0] ?? '';
        $entity = str_replace(['-', '_'], ' ', $entity);
        $entityFa = [
            'user' => 'کاربر', 'role' => 'نقش', 'permission' => 'مجوز', 'scope' => 'محدوده دسترسی',
            'profile' => 'پروفایل', 'company' => 'شرکت', 'branch' => 'شعبه', 'department' => 'واحد سازمانی',
            'partner' => 'شریک', 'item' => 'کالا', 'warehouse' => 'انبار', 'document' => 'سند',
            'voucher' => 'سند حسابداری', 'account' => 'حساب', 'invoice' => 'فاکتور', 'order' => 'سفارش',
            'tenant' => 'مستأجر', 'plan' => 'طرح', 'subscription' => 'اشتراک', 'task' => 'وظیفه',
            'instance' => 'نمونه گردش‌کار', 'definition' => 'تعریف گردش‌کار',
        ][$entity] ?? $entity;

        return $actionFa ? trim($actionFa . ' ' . $entityFa) : $name;
    }

    private function catalog(): array
    {
        return [
            ['code' => 'accounting.account.view', 'name' => 'مشاهده حساب‌ها', 'module_name' => 'حسابداری', 'action_type' => 'READ', 'description' => 'نمودار حساب‌ها و ساختار حسابداری سازمان را می‌بیند.'],
            ['code' => 'accounting.voucher.post', 'name' => 'ثبت نهایی سند حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'EXECUTE', 'description' => 'سند حسابداری پیش‌نویس را قطعی می‌کند.'],
            ['code' => 'accounting.voucher.view', 'name' => 'مشاهده اسناد حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'READ', 'description' => 'اسناد و ردیف‌های حسابداری را مشاهده می‌کند.'],
            ['code' => 'identity.membership_history.view', 'name' => 'مشاهده تاریخچه عضویت', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'رویدادهای عضویت را می‌بیند.'],
            ['code' => 'identity.permission.create', 'name' => 'ایجاد مجوز', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'مجوز جدید در کاتالوگ سازمان ثبت می‌کند.'],
            ['code' => 'identity.permission.delete', 'name' => 'حذف مجوز', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'مجوز را حذف نرم می‌کند.'],
            ['code' => 'identity.permission.update', 'name' => 'ویرایش مجوز', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'عنوان و جزئیات مجوز را ویرایش می‌کند.'],
            ['code' => 'identity.permission.view', 'name' => 'مشاهده مجوزها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'فهرست مجوزهای سازمان را می‌بیند.'],
            ['code' => 'identity.profile.delete', 'name' => 'حذف پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'پروفایل را حذف نرم می‌کند.'],
            ['code' => 'identity.profile.update', 'name' => 'ویرایش پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'اطلاعات پروفایل را به‌روزرسانی می‌کند.'],
            ['code' => 'identity.profile.view', 'name' => 'مشاهده پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'پروفایل کاربری را می‌بیند.'],
            ['code' => 'identity.role.assign', 'name' => 'تخصیص نقش به کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'نقش‌ها را به اعضا نسبت می‌دهد یا برمی‌دارد.'],
            ['code' => 'identity.role.assign-permissions', 'name' => 'تخصیص مجوز به نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'مجوزهای هر نقش را تنظیم می‌کند.'],
            ['code' => 'identity.role.create', 'name' => 'ایجاد نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'نقش جدید تعریف می‌کند.'],
            ['code' => 'identity.role.delete', 'name' => 'حذف نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'حذف نرم نقش.'],
            ['code' => 'identity.role.manage', 'name' => 'مدیریت نقش‌ها (قدیمی)', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'مجوز یکپارچه قدیمی مدیریت نقش.'],
            ['code' => 'identity.role.update', 'name' => 'ویرایش نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'نام و وضعیت نقش را تغییر می‌دهد.'],
            ['code' => 'identity.role.view', 'name' => 'مشاهده نقش‌ها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'فهرست نقش‌ها و سلسله‌مراتب را می‌بیند.'],
            ['code' => 'identity.scope.assign', 'name' => 'تخصیص محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'محدوده را به کاربران نسبت می‌دهد.'],
            ['code' => 'identity.scope.create', 'name' => 'ایجاد محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'محدوده دسترسی جدید تعریف می‌کند.'],
            ['code' => 'identity.scope.delete', 'name' => 'حذف محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'محدوده را حذف نرم می‌کند.'],
            ['code' => 'identity.scope.update', 'name' => 'ویرایش محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'محدوده را ویرایش می‌کند.'],
            ['code' => 'identity.scope.view', 'name' => 'مشاهده محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'محدوده‌های دسترسی را می‌بیند.'],
            ['code' => 'identity.user.create', 'name' => 'ایجاد کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'عضو جدید به سازمان اضافه می‌کند.'],
            ['code' => 'identity.user.delete', 'name' => 'حذف کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'حذف نرم عضویت کاربر.'],
            ['code' => 'identity.user.restore', 'name' => 'بازگردانی کاربر حذف‌شده', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'اعضای حذف‌شده را فعال می‌کند.'],
            ['code' => 'identity.user.update', 'name' => 'ویرایش کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'اطلاعات و وضعیت اعضا را ویرایش می‌کند.'],
            ['code' => 'identity.user.view', 'name' => 'مشاهده کاربران', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'فهرست اعضای سازمان را می‌بیند.'],
            ['code' => 'inventory.document.create', 'name' => 'ایجاد سند موجودی', 'module_name' => 'انبار', 'action_type' => 'CREATE', 'description' => 'سند ورود، خروج یا انتقال موجودی ثبت می‌کند.'],
            ['code' => 'inventory.document.post', 'name' => 'ثبت نهایی سند موجودی', 'module_name' => 'انبار', 'action_type' => 'EXECUTE', 'description' => 'سند موجودی را قطعی می‌کند.'],
            ['code' => 'inventory.document.view', 'name' => 'مشاهده اسناد موجودی', 'module_name' => 'انبار', 'action_type' => 'READ', 'description' => 'اسناد موجودی را می‌بیند.'],
            ['code' => 'inventory.item.create', 'name' => 'ایجاد کالا', 'module_name' => 'انبار', 'action_type' => 'CREATE', 'description' => 'کالای جدید ثبت می‌کند.'],
            ['code' => 'inventory.item.view', 'name' => 'مشاهده کالاها', 'module_name' => 'انبار', 'action_type' => 'READ', 'description' => 'کاتالوگ کالا را می‌بیند.'],
            ['code' => 'inventory.warehouse.view', 'name' => 'مشاهده انبارها', 'module_name' => 'انبار', 'action_type' => 'READ', 'description' => 'فهرست انبارها را می‌بیند.'],
            ['code' => 'masterdata.business-partner.create', 'name' => 'ایجاد طرف تجاری', 'module_name' => 'داده‌های پایه', 'action_type' => 'CREATE', 'description' => 'مشتری یا تأمین‌کننده جدید ثبت می‌کند.'],
            ['code' => 'masterdata.business-partner.update', 'name' => 'ویرایش طرف تجاری', 'module_name' => 'داده‌های پایه', 'action_type' => 'UPDATE', 'description' => 'اطلاعات طرف تجاری را ویرایش می‌کند.'],
            ['code' => 'masterdata.business-partner.view', 'name' => 'مشاهده طرف‌های تجاری', 'module_name' => 'داده‌های پایه', 'action_type' => 'READ', 'description' => 'فهرست مشتریان و تأمین‌کنندگان را می‌بیند.'],
            ['code' => 'organization.branch.create', 'name' => 'ایجاد شعبه', 'module_name' => 'سازمان', 'action_type' => 'CREATE', 'description' => 'شعبه جدید ثبت می‌کند.'],
            ['code' => 'organization.branch.delete', 'name' => 'حذف شعبه', 'module_name' => 'سازمان', 'action_type' => 'DELETE', 'description' => 'شعبه را حذف نرم می‌کند.'],
            ['code' => 'organization.branch.update', 'name' => 'ویرایش شعبه', 'module_name' => 'سازمان', 'action_type' => 'UPDATE', 'description' => 'اطلاعات شعبه را ویرایش می‌کند.'],
            ['code' => 'organization.branch.view', 'name' => 'مشاهده شعب', 'module_name' => 'سازمان', 'action_type' => 'READ', 'description' => 'فهرست شعب را می‌بیند.'],
            ['code' => 'organization.company.create', 'name' => 'ایجاد شرکت', 'module_name' => 'سازمان', 'action_type' => 'CREATE', 'description' => 'شرکت جدید ثبت می‌کند.'],
            ['code' => 'organization.company.delete', 'name' => 'حذف شرکت', 'module_name' => 'سازمان', 'action_type' => 'DELETE', 'description' => 'شرکت را حذف نرم می‌کند.'],
            ['code' => 'organization.company.update', 'name' => 'ویرایش شرکت', 'module_name' => 'سازمان', 'action_type' => 'UPDATE', 'description' => 'اطلاعات شرکت را ویرایش می‌کند.'],
            ['code' => 'organization.company.view', 'name' => 'مشاهده شرکت‌ها', 'module_name' => 'سازمان', 'action_type' => 'READ', 'description' => 'فهرست شرکت‌ها را می‌بیند.'],
            ['code' => 'organization.department.create', 'name' => 'ایجاد واحد سازمانی', 'module_name' => 'سازمان', 'action_type' => 'CREATE', 'description' => 'واحد سازمانی جدید ثبت می‌کند.'],
            ['code' => 'organization.department.delete', 'name' => 'حذف واحد سازمانی', 'module_name' => 'سازمان', 'action_type' => 'DELETE', 'description' => 'واحد را حذف نرم می‌کند.'],
            ['code' => 'organization.department.update', 'name' => 'ویرایش واحد سازمانی', 'module_name' => 'سازمان', 'action_type' => 'UPDATE', 'description' => 'واحد را ویرایش می‌کند.'],
            ['code' => 'organization.department.view', 'name' => 'مشاهده واحدها', 'module_name' => 'سازمان', 'action_type' => 'READ', 'description' => 'فهرست واحدهای سازمانی را می‌بیند.'],
            ['code' => 'partner.activity_log.create', 'name' => 'ایجاد لاگ فعالیت شریک', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'رویداد فعالیت شریک را ثبت می‌کند.'],
            ['code' => 'partner.activity_log.view', 'name' => 'مشاهده لاگ فعالیت شریک', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'لاگ فعالیت‌های شریک را می‌بیند.'],
            ['code' => 'partner.agreement.create', 'name' => 'ایجاد توافق‌نامه شریک', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'توافق‌نامه جدید ثبت می‌کند.'],
            ['code' => 'partner.agreement.delete', 'name' => 'حذف توافق‌نامه شریک', 'module_name' => 'لایه شریک', 'action_type' => 'DELETE', 'description' => 'توافق‌نامه را حذف می‌کند.'],
            ['code' => 'partner.agreement.update', 'name' => 'ویرایش توافق‌نامه شریک', 'module_name' => 'لایه شریک', 'action_type' => 'UPDATE', 'description' => 'توافق‌نامه را ویرایش می‌کند.'],
            ['code' => 'partner.agreement.view', 'name' => 'مشاهده توافق‌نامه‌های شریک', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'توافق‌نامه‌ها را می‌بیند.'],
            ['code' => 'partner.assignment.create', 'name' => 'ایجاد تخصیص مستأجر', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'سازمان را به شریک تخصیص می‌دهد.'],
            ['code' => 'partner.assignment.delete', 'name' => 'حذف تخصیص مستأجر', 'module_name' => 'لایه شریک', 'action_type' => 'DELETE', 'description' => 'تخصیص را حذف می‌کند.'],
            ['code' => 'partner.assignment.update', 'name' => 'ویرایش تخصیص مستأجر', 'module_name' => 'لایه شریک', 'action_type' => 'UPDATE', 'description' => 'تخصیص را ویرایش می‌کند.'],
            ['code' => 'partner.assignment.view', 'name' => 'مشاهده تخصیص مستأجر به شریک', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'تخصیص‌ها را می‌بیند.'],
            ['code' => 'partner.bank_account.create', 'name' => 'ایجاد حساب بانکی شریک', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'حساب بانکی شریک ثبت می‌کند.'],
            ['code' => 'partner.bank_account.delete', 'name' => 'حذف حساب بانکی شریک', 'module_name' => 'لایه شریک', 'action_type' => 'DELETE', 'description' => 'حساب بانکی را حذف می‌کند.'],
            ['code' => 'partner.bank_account.update', 'name' => 'ویرایش حساب بانکی شریک', 'module_name' => 'لایه شریک', 'action_type' => 'UPDATE', 'description' => 'حساب بانکی را ویرایش می‌کند.'],
            ['code' => 'partner.bank_account.view', 'name' => 'مشاهده حساب‌های بانکی شریک', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'حساب‌های بانکی را می‌بیند.'],
            ['code' => 'partner.commission.create', 'name' => 'ایجاد کمیسیون', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'رکورد کمیسیون ثبت می‌کند.'],
            ['code' => 'partner.commission.delete', 'name' => 'حذف کمیسیون', 'module_name' => 'لایه شریک', 'action_type' => 'DELETE', 'description' => 'کمیسیون را حذف می‌کند.'],
            ['code' => 'partner.commission.update', 'name' => 'ویرایش کمیسیون', 'module_name' => 'لایه شریک', 'action_type' => 'UPDATE', 'description' => 'کمیسیون را ویرایش می‌کند.'],
            ['code' => 'partner.commission.view', 'name' => 'مشاهده کمیسیون‌ها', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'کمیسیون‌ها را می‌بیند.'],
            ['code' => 'partner.commission_rule.create', 'name' => 'ایجاد قاعده کمیسیون', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'قاعده کمیسیون تعریف می‌کند.'],
            ['code' => 'partner.commission_rule.delete', 'name' => 'حذف قاعده کمیسیون', 'module_name' => 'لایه شریک', 'action_type' => 'DELETE', 'description' => 'قاعده را حذف می‌کند.'],
            ['code' => 'partner.commission_rule.update', 'name' => 'ویرایش قاعده کمیسیون', 'module_name' => 'لایه شریک', 'action_type' => 'UPDATE', 'description' => 'قاعده را ویرایش می‌کند.'],
            ['code' => 'partner.commission_rule.view', 'name' => 'مشاهده قواعد کمیسیون', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'قواعد کمیسیون را می‌بیند.'],
            ['code' => 'partner.contact.create', 'name' => 'ایجاد مخاطب شریک', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'مخاطب شریک ثبت می‌کند.'],
            ['code' => 'partner.contact.delete', 'name' => 'حذف مخاطب شریک', 'module_name' => 'لایه شریک', 'action_type' => 'DELETE', 'description' => 'مخاطب را حذف می‌کند.'],
            ['code' => 'partner.contact.update', 'name' => 'ویرایش مخاطب شریک', 'module_name' => 'لایه شریک', 'action_type' => 'UPDATE', 'description' => 'مخاطب را ویرایش می‌کند.'],
            ['code' => 'partner.contact.view', 'name' => 'مشاهده مخاطبین شریک', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'مخاطبین را می‌بیند.'],
            ['code' => 'partner.document.create', 'name' => 'ایجاد سند شریک', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'سند شریک ثبت می‌کند.'],
            ['code' => 'partner.document.delete', 'name' => 'حذف سند شریک', 'module_name' => 'لایه شریک', 'action_type' => 'DELETE', 'description' => 'سند را حذف می‌کند.'],
            ['code' => 'partner.document.update', 'name' => 'ویرایش سند شریک', 'module_name' => 'لایه شریک', 'action_type' => 'UPDATE', 'description' => 'سند را ویرایش می‌کند.'],
            ['code' => 'partner.document.view', 'name' => 'مشاهده اسناد شریک', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'اسناد شریک را می‌بیند.'],
            ['code' => 'partner.partner.create', 'name' => 'ایجاد شریک', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'شریک تجاری جدید ثبت می‌کند.'],
            ['code' => 'partner.partner.delete', 'name' => 'حذف شریک', 'module_name' => 'لایه شریک', 'action_type' => 'DELETE', 'description' => 'شریک را حذف نرم می‌کند.'],
            ['code' => 'partner.partner.update', 'name' => 'ویرایش شریک', 'module_name' => 'لایه شریک', 'action_type' => 'UPDATE', 'description' => 'اطلاعات شریک را ویرایش می‌کند.'],
            ['code' => 'partner.partner.view', 'name' => 'مشاهده شرکا', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'فهرست شرکا را می‌بیند.'],
            ['code' => 'partner.partner_user.create', 'name' => 'ایجاد کاربر شریک', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'کاربر شریک ثبت می‌کند.'],
            ['code' => 'partner.partner_user.delete', 'name' => 'حذف کاربر شریک', 'module_name' => 'لایه شریک', 'action_type' => 'DELETE', 'description' => 'کاربر شریک را حذف می‌کند.'],
            ['code' => 'partner.partner_user.update', 'name' => 'ویرایش کاربر شریک', 'module_name' => 'لایه شریک', 'action_type' => 'UPDATE', 'description' => 'کاربر شریک را ویرایش می‌کند.'],
            ['code' => 'partner.partner_user.view', 'name' => 'مشاهده کاربران شریک', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'کاربران شریک را می‌بیند.'],
            ['code' => 'partner.payout.create', 'name' => 'ایجاد پرداخت شریک', 'module_name' => 'لایه شریک', 'action_type' => 'CREATE', 'description' => 'پرداخت به شریک ثبت می‌کند.'],
            ['code' => 'partner.payout.delete', 'name' => 'حذف پرداخت شریک', 'module_name' => 'لایه شریک', 'action_type' => 'DELETE', 'description' => 'پرداخت را حذف می‌کند.'],
            ['code' => 'partner.payout.update', 'name' => 'ویرایش پرداخت شریک', 'module_name' => 'لایه شریک', 'action_type' => 'UPDATE', 'description' => 'پرداخت را ویرایش می‌کند.'],
            ['code' => 'partner.payout.view', 'name' => 'مشاهده پرداخت‌های شریک', 'module_name' => 'لایه شریک', 'action_type' => 'READ', 'description' => 'پرداخت‌ها را می‌بیند.'],
            ['code' => 'procurement.cash-transaction.create', 'name' => 'ایجاد تراکنش نقدی', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'تراکنش نقدی ثبت می‌کند.'],
            ['code' => 'procurement.cash-transaction.view', 'name' => 'مشاهده تراکنش نقدی', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'تراکنش‌های نقدی را می‌بیند.'],
            ['code' => 'procurement.payment-schedule.view', 'name' => 'مشاهده برنامه پرداخت', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'برنامه‌های پرداخت را می‌بیند.'],
            ['code' => 'procurement.purchase-invoice.create', 'name' => 'ایجاد فاکتور خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'فاکتور خرید ثبت می‌کند.'],
            ['code' => 'procurement.purchase-invoice.post', 'name' => 'ثبت نهایی فاکتور خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'فاکتور خرید را قطعی می‌کند.'],
            ['code' => 'procurement.purchase-invoice.view', 'name' => 'مشاهده فاکتور خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'فاکتورهای خرید را می‌بیند.'],
            ['code' => 'procurement.purchase-order.create', 'name' => 'ایجاد سفارش خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'سفارش خرید ثبت می‌کند.'],
            ['code' => 'procurement.purchase-receipt.create', 'name' => 'ایجاد رسید خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'رسید خرید ثبت می‌کند.'],
            ['code' => 'procurement.purchase-receipt.post', 'name' => 'ثبت نهایی رسید خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'رسید خرید را قطعی می‌کند.'],
            ['code' => 'procurement.purchase-receipt.view', 'name' => 'مشاهده رسید خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'رسیدهای خرید را می‌بیند.'],
            ['code' => 'procurement.purchase-requisition.approve', 'name' => 'تأیید درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'درخواست خرید را تأیید می‌کند.'],
            ['code' => 'procurement.purchase-requisition.create', 'name' => 'ایجاد درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'درخواست خرید ثبت می‌کند.'],
            ['code' => 'procurement.purchase-requisition.submit', 'name' => 'ارسال درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'درخواست را برای تأیید ارسال می‌کند.'],
            ['code' => 'procurement.purchase-requisition.view', 'name' => 'مشاهده درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'درخواست‌های خرید را می‌بیند.'],
            ['code' => 'procurement.return-order.create', 'name' => 'ایجاد سفارش مرجوعی', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'سفارش مرجوعی ثبت می‌کند.'],
            ['code' => 'procurement.sales-delivery.create', 'name' => 'ایجاد حواله فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'حواله فروش ثبت می‌کند.'],
            ['code' => 'procurement.sales-delivery.post', 'name' => 'ثبت نهایی حواله فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'حواله فروش را قطعی می‌کند.'],
            ['code' => 'procurement.sales-delivery.view', 'name' => 'مشاهده حواله فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'حواله‌های فروش را می‌بیند.'],
            ['code' => 'procurement.sales-invoice.create', 'name' => 'ایجاد فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'فاکتور فروش ثبت می‌کند.'],
            ['code' => 'procurement.sales-invoice.post', 'name' => 'ثبت نهایی فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'فاکتور فروش را قطعی می‌کند.'],
            ['code' => 'procurement.sales-invoice.update', 'name' => 'ویرایش فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'UPDATE', 'description' => 'فاکتور فروش را ویرایش می‌کند.'],
            ['code' => 'procurement.sales-invoice.view', 'name' => 'مشاهده فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'فاکتورهای فروش را می‌بیند.'],
            ['code' => 'procurement.sales-order.confirm', 'name' => 'تأیید سفارش فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'سفارش فروش را تأیید می‌کند.'],
            ['code' => 'procurement.sales-order.create', 'name' => 'ایجاد سفارش فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'سفارش فروش ثبت می‌کند.'],
            ['code' => 'procurement.sales-order.view', 'name' => 'مشاهده سفارش فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'سفارش‌های فروش را می‌بیند.'],
            ['code' => 'procurement.sales-quotation.create', 'name' => 'ایجاد پیش‌فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'پیش‌فاکتور فروش ثبت می‌کند.'],
            ['code' => 'saas.plan.view', 'name' => 'مشاهده طرح‌ها', 'module_name' => 'مدیریت پلتفرم', 'action_type' => 'READ', 'description' => 'طرح‌های اشتراک را می‌بیند.'],
            ['code' => 'saas.subscription.view', 'name' => 'مشاهده اشتراک‌ها', 'module_name' => 'مدیریت پلتفرم', 'action_type' => 'READ', 'description' => 'اشتراک‌ها را می‌بیند.'],
            ['code' => 'saas.tenant.create', 'name' => 'ایجاد مستأجر', 'module_name' => 'مدیریت پلتفرم', 'action_type' => 'CREATE', 'description' => 'سازمان جدید در پلتفرم ثبت می‌کند.'],
            ['code' => 'saas.tenant.update', 'name' => 'ویرایش مستأجر', 'module_name' => 'مدیریت پلتفرم', 'action_type' => 'UPDATE', 'description' => 'اطلاعات سازمان را ویرایش می‌کند.'],
            ['code' => 'saas.tenant.view', 'name' => 'مشاهده مستأجران', 'module_name' => 'مدیریت پلتفرم', 'action_type' => 'READ', 'description' => 'فهرست سازمان‌های پلتفرم را می‌بیند.'],
            ['code' => 'workflow.definition.manage', 'name' => 'مدیریت تعریف گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'EXECUTE', 'description' => 'تعاریف گردش‌کار را مدیریت می‌کند.'],
            ['code' => 'workflow.instance.start', 'name' => 'شروع نمونه گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'EXECUTE', 'description' => 'نمونه گردش‌کار را شروع می‌کند.'],
            ['code' => 'workflow.instance.view', 'name' => 'مشاهده نمونه گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'READ', 'description' => 'وضعیت نمونه‌ها را می‌بیند.'],
            ['code' => 'workflow.task.complete', 'name' => 'انجام وظیفه گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'EXECUTE', 'description' => 'وظیفه کارتابل را تکمیل می‌کند.'],
            ['code' => 'workflow.task.view', 'name' => 'مشاهده کارتابل', 'module_name' => 'گردش کار', 'action_type' => 'READ', 'description' => 'وظایف منتظر اقدام را می‌بیند.'],
        ];
    }
}
