<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Force Persian labels for tenant_permissions (by code) + module map + heuristic for leftovers.
 * Safe to re-run. PostgreSQL-safe (no Unicode regex in SQL).
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
            'PartnerLayer' => 'شرکای تجاری',
            'ProcurementSales' => 'خرید و فروش',
            'SaasAdmin' => 'مدیریت پلتفرم',
            'SaasPlatform' => 'پلتفرم SaaS',
            'Workflow' => 'گردش کار',
            'DocumentManagement' => 'مدیریت اسناد',
            'Manufacturing' => 'تولید',
            'لایه شریک' => 'شرکای تجاری',
        ];
        foreach ($moduleMap as $en => $fa) {
            DB::table('tenant_permissions')
                ->where('module_name', $en)
                ->update(['module_name' => $fa, 'updated_at' => now()]);
        }

        $rows = DB::table('tenant_permissions')
            ->whereRaw("name ~ '[A-Za-z]{3,}'")
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

        $entityParts = array_slice($parts, 0, -1);
        if (count($entityParts) > 1) {
            array_shift($entityParts);
        }
        $entityRaw = str_replace(['-', '_'], ' ', implode(' ', $entityParts));

        $wordMap = [
            'user' => 'کاربر', 'role' => 'نقش', 'permission' => 'مجوز', 'scope' => 'محدوده دسترسی',
            'profile' => 'پروفایل', 'company' => 'شرکت', 'branch' => 'شعبه', 'department' => 'واحد سازمانی',
            'partner' => 'طرف تجاری', 'item' => 'قلم', 'warehouse' => 'انبار', 'document' => 'سند',
            'voucher' => 'سند حسابداری', 'account' => 'حساب', 'invoice' => 'فاکتور', 'order' => 'سفارش',
            'tax' => 'مالیات', 'transaction' => 'تراکنش', 'cash' => 'نقدی', 'payment' => 'پرداخت',
            'schedule' => 'برنامه', 'purchase' => 'خرید', 'sales' => 'فروش', 'receipt' => 'رسید',
            'requisition' => 'درخواست', 'delivery' => 'حواله', 'quotation' => 'پیش‌فاکتور', 'return' => 'مرجوعی',
            'tenant' => 'سازمان', 'plan' => 'طرح', 'subscription' => 'اشتراک', 'task' => 'وظیفه',
            'instance' => 'نمونه', 'definition' => 'تعریف', 'membership' => 'عضویت', 'history' => 'تاریخچه',
            'business' => 'تجاری', 'contact' => 'مخاطب', 'commission' => 'کمیسیون', 'rule' => 'قاعده',
            'payout' => 'تسویه', 'agreement' => 'توافق‌نامه', 'assignment' => 'تخصیص', 'bank' => 'بانکی',
            'activity' => 'فعالیت', 'log' => 'لاگ', 'admin' => 'مدیریت',
        ];

        $words = preg_split('/\s+/', strtolower($entityRaw)) ?: [];
        $entityFa = [];
        foreach ($words as $w) {
            $entityFa[] = $wordMap[$w] ?? $w;
        }
        $entity = trim(implode(' ', $entityFa));

        return $actionFa ? trim($actionFa . ' ' . $entity) : $name;
    }

    private function catalog(): array
    {
        return [
            ['code' => 'accounting.account.view', 'name' => 'مشاهده حساب‌ها', 'module_name' => 'حسابداری', 'action_type' => 'READ', 'description' => 'نمودار حساب‌ها را می‌بیند.'],
            ['code' => 'accounting.voucher.view', 'name' => 'مشاهده اسناد حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'READ', 'description' => 'اسناد حسابداری را می‌بیند.'],
            ['code' => 'accounting.voucher.create', 'name' => 'ایجاد سند حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'CREATE', 'description' => 'سند حسابداری جدید ثبت می‌کند.'],
            ['code' => 'accounting.voucher.update', 'name' => 'ویرایش سند حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'UPDATE', 'description' => 'سند حسابداری پیش‌نویس را ویرایش می‌کند.'],
            ['code' => 'accounting.voucher.delete', 'name' => 'حذف سند حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'DELETE', 'description' => 'سند حسابداری را حذف نرم می‌کند.'],
            ['code' => 'accounting.voucher.post', 'name' => 'ثبت نهایی سند حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'EXECUTE', 'description' => 'سند حسابداری را قطعی می‌کند.'],
            ['code' => 'accounting.voucher_item.view', 'name' => 'مشاهده قلم سند حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'READ', 'description' => 'ردیف‌های سند حسابداری را می‌بیند.'],
            ['code' => 'accounting.voucher_item.create', 'name' => 'ایجاد قلم سند حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'CREATE', 'description' => 'ردیف جدید در سند حسابداری ثبت می‌کند.'],
            ['code' => 'accounting.voucher_item.update', 'name' => 'ویرایش قلم سند حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'UPDATE', 'description' => 'ردیف سند حسابداری را ویرایش می‌کند.'],
            ['code' => 'accounting.voucher_item.delete', 'name' => 'حذف قلم سند حسابداری', 'module_name' => 'حسابداری', 'action_type' => 'DELETE', 'description' => 'ردیف سند حسابداری را حذف می‌کند.'],
            ['code' => 'accounting.tax_transaction.view', 'name' => 'مشاهده تراکنش مالیاتی', 'module_name' => 'حسابداری', 'action_type' => 'READ', 'description' => 'تراکنش‌های مالیاتی را می‌بیند.'],
            ['code' => 'accounting.tax_transaction.create', 'name' => 'ایجاد تراکنش مالیاتی', 'module_name' => 'حسابداری', 'action_type' => 'CREATE', 'description' => 'تراکنش مالیاتی جدید ثبت می‌کند.'],
            ['code' => 'accounting.tax_transaction.update', 'name' => 'ویرایش تراکنش مالیاتی', 'module_name' => 'حسابداری', 'action_type' => 'UPDATE', 'description' => 'تراکنش مالیاتی را ویرایش می‌کند.'],
            ['code' => 'accounting.tax_transaction.delete', 'name' => 'حذف تراکنش مالیاتی', 'module_name' => 'حسابداری', 'action_type' => 'DELETE', 'description' => 'تراکنش مالیاتی را حذف می‌کند.'],
            ['code' => 'identity.user.view', 'name' => 'مشاهده کاربران', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'فهرست اعضای سازمان را می‌بیند.'],
            ['code' => 'identity.user.create', 'name' => 'ایجاد کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'عضو جدید اضافه می‌کند.'],
            ['code' => 'identity.user.update', 'name' => 'ویرایش کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'اطلاعات عضو را ویرایش می‌کند.'],
            ['code' => 'identity.user.delete', 'name' => 'حذف کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'عضویت را حذف نرم می‌کند.'],
            ['code' => 'identity.user.restore', 'name' => 'بازگردانی کاربر حذف‌شده', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'عضو حذف‌شده را فعال می‌کند.'],
            ['code' => 'identity.role.view', 'name' => 'مشاهده نقش‌ها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'فهرست نقش‌ها را می‌بیند.'],
            ['code' => 'identity.role.create', 'name' => 'ایجاد نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'نقش جدید تعریف می‌کند.'],
            ['code' => 'identity.role.update', 'name' => 'ویرایش نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'نقش را ویرایش می‌کند.'],
            ['code' => 'identity.role.delete', 'name' => 'حذف نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'نقش را حذف نرم می‌کند.'],
            ['code' => 'identity.role.assign', 'name' => 'تخصیص نقش به کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'نقش را به عضو نسبت می‌دهد.'],
            ['code' => 'identity.role.assign-permissions', 'name' => 'تخصیص مجوز به نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'مجوزهای نقش را تنظیم می‌کند.'],
            ['code' => 'identity.role.manage', 'name' => 'مدیریت نقش‌ها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'مدیریت یکپارچه نقش‌ها.'],
            ['code' => 'identity.permission.view', 'name' => 'مشاهده مجوزها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'فهرست مجوزها را می‌بیند.'],
            ['code' => 'identity.permission.create', 'name' => 'ایجاد مجوز', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'مجوز جدید ثبت می‌کند.'],
            ['code' => 'identity.permission.update', 'name' => 'ویرایش مجوز', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'مجوز را ویرایش می‌کند.'],
            ['code' => 'identity.permission.delete', 'name' => 'حذف مجوز', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'مجوز را حذف می‌کند.'],
            ['code' => 'identity.scope.view', 'name' => 'مشاهده محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'محدوده‌های دسترسی را می‌بیند.'],
            ['code' => 'identity.scope.create', 'name' => 'ایجاد محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE', 'description' => 'محدوده جدید تعریف می‌کند.'],
            ['code' => 'identity.scope.update', 'name' => 'ویرایش محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'محدوده را ویرایش می‌کند.'],
            ['code' => 'identity.scope.delete', 'name' => 'حذف محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'محدوده را حذف می‌کند.'],
            ['code' => 'identity.scope.assign', 'name' => 'تخصیص محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE', 'description' => 'محدوده را به کاربر نسبت می‌دهد.'],
            ['code' => 'identity.profile.view', 'name' => 'مشاهده پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'پروفایل را می‌بیند.'],
            ['code' => 'identity.profile.update', 'name' => 'ویرایش پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE', 'description' => 'پروفایل را ویرایش می‌کند.'],
            ['code' => 'identity.profile.delete', 'name' => 'حذف پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE', 'description' => 'پروفایل را حذف می‌کند.'],
            ['code' => 'identity.membership_history.view', 'name' => 'مشاهده تاریخچه عضویت', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ', 'description' => 'رویدادهای عضویت را می‌بیند.'],
            ['code' => 'inventory.item.view', 'name' => 'مشاهده کالاها', 'module_name' => 'انبار', 'action_type' => 'READ', 'description' => 'کاتالوگ کالا را می‌بیند.'],
            ['code' => 'inventory.item.create', 'name' => 'ایجاد کالا', 'module_name' => 'انبار', 'action_type' => 'CREATE', 'description' => 'کالای جدید ثبت می‌کند.'],
            ['code' => 'inventory.warehouse.view', 'name' => 'مشاهده انبارها', 'module_name' => 'انبار', 'action_type' => 'READ', 'description' => 'فهرست انبارها را می‌بیند.'],
            ['code' => 'inventory.document.view', 'name' => 'مشاهده اسناد موجودی', 'module_name' => 'انبار', 'action_type' => 'READ', 'description' => 'اسناد موجودی را می‌بیند.'],
            ['code' => 'inventory.document.create', 'name' => 'ایجاد سند موجودی', 'module_name' => 'انبار', 'action_type' => 'CREATE', 'description' => 'سند موجودی ثبت می‌کند.'],
            ['code' => 'inventory.document.post', 'name' => 'ثبت نهایی سند موجودی', 'module_name' => 'انبار', 'action_type' => 'EXECUTE', 'description' => 'سند موجودی را قطعی می‌کند.'],
            ['code' => 'masterdata.business-partner.view', 'name' => 'مشاهده طرف‌های تجاری', 'module_name' => 'داده‌های پایه', 'action_type' => 'READ', 'description' => 'مشتریان و تأمین‌کنندگان را می‌بیند.'],
            ['code' => 'masterdata.business-partner.create', 'name' => 'ایجاد طرف تجاری', 'module_name' => 'داده‌های پایه', 'action_type' => 'CREATE', 'description' => 'طرف تجاری جدید ثبت می‌کند.'],
            ['code' => 'masterdata.business-partner.update', 'name' => 'ویرایش طرف تجاری', 'module_name' => 'داده‌های پایه', 'action_type' => 'UPDATE', 'description' => 'طرف تجاری را ویرایش می‌کند.'],
            ['code' => 'organization.company.view', 'name' => 'مشاهده شرکت‌ها', 'module_name' => 'سازمان', 'action_type' => 'READ', 'description' => 'فهرست شرکت‌ها را می‌بیند.'],
            ['code' => 'organization.company.create', 'name' => 'ایجاد شرکت', 'module_name' => 'سازمان', 'action_type' => 'CREATE', 'description' => 'شرکت جدید ثبت می‌کند.'],
            ['code' => 'organization.company.update', 'name' => 'ویرایش شرکت', 'module_name' => 'سازمان', 'action_type' => 'UPDATE', 'description' => 'شرکت را ویرایش می‌کند.'],
            ['code' => 'organization.company.delete', 'name' => 'حذف شرکت', 'module_name' => 'سازمان', 'action_type' => 'DELETE', 'description' => 'شرکت را حذف می‌کند.'],
            ['code' => 'organization.branch.view', 'name' => 'مشاهده شعب', 'module_name' => 'سازمان', 'action_type' => 'READ', 'description' => 'فهرست شعب را می‌بیند.'],
            ['code' => 'organization.branch.create', 'name' => 'ایجاد شعبه', 'module_name' => 'سازمان', 'action_type' => 'CREATE', 'description' => 'شعبه جدید ثبت می‌کند.'],
            ['code' => 'organization.branch.update', 'name' => 'ویرایش شعبه', 'module_name' => 'سازمان', 'action_type' => 'UPDATE', 'description' => 'شعبه را ویرایش می‌کند.'],
            ['code' => 'organization.branch.delete', 'name' => 'حذف شعبه', 'module_name' => 'سازمان', 'action_type' => 'DELETE', 'description' => 'شعبه را حذف می‌کند.'],
            ['code' => 'organization.department.view', 'name' => 'مشاهده واحدها', 'module_name' => 'سازمان', 'action_type' => 'READ', 'description' => 'واحدهای سازمانی را می‌بیند.'],
            ['code' => 'organization.department.create', 'name' => 'ایجاد واحد سازمانی', 'module_name' => 'سازمان', 'action_type' => 'CREATE', 'description' => 'واحد جدید ثبت می‌کند.'],
            ['code' => 'organization.department.update', 'name' => 'ویرایش واحد سازمانی', 'module_name' => 'سازمان', 'action_type' => 'UPDATE', 'description' => 'واحد را ویرایش می‌کند.'],
            ['code' => 'organization.department.delete', 'name' => 'حذف واحد سازمانی', 'module_name' => 'سازمان', 'action_type' => 'DELETE', 'description' => 'واحد را حذف می‌کند.'],
            ['code' => 'procurement.purchase-order.create', 'name' => 'ایجاد سفارش خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'سفارش خرید ثبت می‌کند.'],
            ['code' => 'procurement.purchase-receipt.create', 'name' => 'ایجاد رسید خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'رسید خرید ثبت می‌کند.'],
            ['code' => 'procurement.purchase-receipt.view', 'name' => 'مشاهده رسید خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'رسیدهای خرید را می‌بیند.'],
            ['code' => 'procurement.purchase-receipt.post', 'name' => 'ثبت نهایی رسید خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'رسید خرید را قطعی می‌کند.'],
            ['code' => 'procurement.sales-order.create', 'name' => 'ایجاد سفارش فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'سفارش فروش ثبت می‌کند.'],
            ['code' => 'procurement.sales-order.view', 'name' => 'مشاهده سفارش فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'سفارش‌های فروش را می‌بیند.'],
            ['code' => 'procurement.sales-order.confirm', 'name' => 'تأیید سفارش فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'سفارش فروش را تأیید می‌کند.'],
            ['code' => 'procurement.sales-invoice.view', 'name' => 'مشاهده فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'فاکتورهای فروش را می‌بیند.'],
            ['code' => 'procurement.sales-invoice.create', 'name' => 'ایجاد فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'فاکتور فروش ثبت می‌کند.'],
            ['code' => 'procurement.sales-invoice.update', 'name' => 'ویرایش فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'UPDATE', 'description' => 'فاکتور فروش را ویرایش می‌کند.'],
            ['code' => 'procurement.sales-invoice.post', 'name' => 'ثبت نهایی فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'فاکتور فروش را قطعی می‌کند.'],
            ['code' => 'procurement.purchase-invoice.view', 'name' => 'مشاهده فاکتور خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'فاکتورهای خرید را می‌بیند.'],
            ['code' => 'procurement.purchase-invoice.create', 'name' => 'ایجاد فاکتور خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'فاکتور خرید ثبت می‌کند.'],
            ['code' => 'procurement.purchase-invoice.post', 'name' => 'ثبت نهایی فاکتور خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'فاکتور خرید را قطعی می‌کند.'],
            ['code' => 'procurement.cash-transaction.create', 'name' => 'ایجاد تراکنش نقدی', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'تراکنش نقدی ثبت می‌کند.'],
            ['code' => 'procurement.cash-transaction.view', 'name' => 'مشاهده تراکنش نقدی', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'تراکنش‌های نقدی را می‌بیند.'],
            ['code' => 'procurement.payment-schedule.view', 'name' => 'مشاهده برنامه پرداخت', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'برنامه‌های پرداخت را می‌بیند.'],
            ['code' => 'procurement.purchase-requisition.view', 'name' => 'مشاهده درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'درخواست‌های خرید را می‌بیند.'],
            ['code' => 'procurement.purchase-requisition.create', 'name' => 'ایجاد درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'درخواست خرید ثبت می‌کند.'],
            ['code' => 'procurement.purchase-requisition.submit', 'name' => 'ارسال درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'درخواست را ارسال می‌کند.'],
            ['code' => 'procurement.purchase-requisition.approve', 'name' => 'تأیید درخواست خرید', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'درخواست خرید را تأیید می‌کند.'],
            ['code' => 'procurement.sales-delivery.create', 'name' => 'ایجاد حواله فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'حواله فروش ثبت می‌کند.'],
            ['code' => 'procurement.sales-delivery.view', 'name' => 'مشاهده حواله فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'READ', 'description' => 'حواله‌های فروش را می‌بیند.'],
            ['code' => 'procurement.sales-delivery.post', 'name' => 'ثبت نهایی حواله فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'EXECUTE', 'description' => 'حواله فروش را قطعی می‌کند.'],
            ['code' => 'procurement.sales-quotation.create', 'name' => 'ایجاد پیش‌فاکتور فروش', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'پیش‌فاکتور فروش ثبت می‌کند.'],
            ['code' => 'procurement.return-order.create', 'name' => 'ایجاد سفارش مرجوعی', 'module_name' => 'خرید و فروش', 'action_type' => 'CREATE', 'description' => 'سفارش مرجوعی ثبت می‌کند.'],
            ['code' => 'workflow.definition.manage', 'name' => 'مدیریت تعریف گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'EXECUTE', 'description' => 'تعاریف گردش‌کار را مدیریت می‌کند.'],
            ['code' => 'workflow.instance.start', 'name' => 'شروع نمونه گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'EXECUTE', 'description' => 'نمونه گردش‌کار را شروع می‌کند.'],
            ['code' => 'workflow.instance.view', 'name' => 'مشاهده نمونه گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'READ', 'description' => 'وضعیت نمونه‌ها را می‌بیند.'],
            ['code' => 'workflow.task.view', 'name' => 'مشاهده کارتابل', 'module_name' => 'گردش کار', 'action_type' => 'READ', 'description' => 'وظایف کارتابل را می‌بیند.'],
            ['code' => 'workflow.task.complete', 'name' => 'انجام وظیفه گردش‌کار', 'module_name' => 'گردش کار', 'action_type' => 'EXECUTE', 'description' => 'وظیفه را تکمیل می‌کند.'],
            ['code' => 'saas.tenant.view', 'name' => 'مشاهده سازمان‌های پلتفرم', 'module_name' => 'مدیریت پلتفرم', 'action_type' => 'READ', 'description' => 'فهرست سازمان‌های پلتفرم را می‌بیند.'],
            ['code' => 'saas.tenant.create', 'name' => 'ایجاد سازمان پلتفرم', 'module_name' => 'مدیریت پلتفرم', 'action_type' => 'CREATE', 'description' => 'سازمان جدید در پلتفرم ثبت می‌کند.'],
            ['code' => 'saas.tenant.update', 'name' => 'ویرایش سازمان پلتفرم', 'module_name' => 'مدیریت پلتفرم', 'action_type' => 'UPDATE', 'description' => 'سازمان پلتفرم را ویرایش می‌کند.'],
            ['code' => 'saas.plan.view', 'name' => 'مشاهده طرح‌ها', 'module_name' => 'مدیریت پلتفرم', 'action_type' => 'READ', 'description' => 'طرح‌های اشتراک را می‌بیند.'],
            ['code' => 'saas.subscription.view', 'name' => 'مشاهده اشتراک‌ها', 'module_name' => 'مدیریت پلتفرم', 'action_type' => 'READ', 'description' => 'اشتراک‌ها را می‌بیند.'],
        ];
    }
}
