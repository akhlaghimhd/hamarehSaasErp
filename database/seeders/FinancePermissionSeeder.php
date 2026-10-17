<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * FIN-P0..P6 finance.* permission codes (idempotent insert-only).
 * Existing rows are never updated here — use EnrichPermissionDescriptionsSeeder
 * for selective description enrichment of weak rows.
 */
class FinancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('tenant_permissions') || ! Schema::hasTable('tenants')) {
            return;
        }

        $perms = [
            ['code' => 'finance.coa.view', 'name' => 'مشاهده سرفصل حساب‌ها', 'action_type' => 'READ', 'description' => 'مشاهده درخت سرفصل حساب‌ها و جزئیات هر حساب. برای گزارش و انتخاب حساب در اسناد لازم است؛ تغییری در ساختار حساب ایجاد نمی‌کند.'],
            ['code' => 'finance.coa.create', 'name' => 'ایجاد سرفصل حساب', 'action_type' => 'CREATE', 'description' => 'افزودن حساب جدید در سلسله‌مراتب سرفصل. روی کدینگ و گزارش‌های آتی اثر دارد؛ فقط افراد مسلط به ساختار حسابداری.'],
            ['code' => 'finance.coa.update', 'name' => 'ویرایش سرفصل حساب', 'action_type' => 'UPDATE', 'description' => 'ویرایش نام و ویژگی‌های حساب‌های غیرریشه. حساب ریشه سیستم قفل است و قابل ویرایش نیست.'],
            ['code' => 'finance.coa.delete', 'name' => 'حذف سرفصل حساب', 'action_type' => 'DELETE', 'description' => 'حذف نرم حساب‌هایی که در اسناد استفاده نشده‌اند. حساب ریشه و حساب‌های دارای گردش قابل حذف نیستند.'],
            ['code' => 'finance.journal.view', 'name' => 'مشاهده اسناد حسابداری', 'action_type' => 'READ', 'description' => 'مشاهده فهرست اسناد حسابداری (پیش‌نویس، ثبت‌شده و برگشتی) و جزئیات سطرها. بدون مجوز ایجاد یا ثبت قطعی؛ فقط خواندن برای کنترل و گزارش.'],
            ['code' => 'finance.journal.create', 'name' => 'ایجاد پیش‌نویس سند حسابداری', 'action_type' => 'CREATE', 'description' => 'ایجاد پیش‌نویس سند حسابداری جدید برای شرکت‌هایی که مجاز به عملیات هستید. تا وقتی ثبت قطعی نشود در دفتر کل اثر ندارد.'],
            ['code' => 'finance.journal.update', 'name' => 'ویرایش پیش‌نویس سند حسابداری', 'action_type' => 'UPDATE', 'description' => 'ویرایش پیش‌نویس‌های ذخیره‌شده (تاریخ، شرح، سطرها). اسناد ثبت‌شده با این مجوز قابل تغییر نیستند.'],
            ['code' => 'finance.journal.delete', 'name' => 'حذف پیش‌نویس سند حسابداری', 'action_type' => 'DELETE', 'description' => 'حذف نرم پیش‌نویس‌هایی که قوانین سیستمی اجازه می‌دهد. اسناد ثبت‌شده حذف نمی‌شوند.'],
            ['code' => 'finance.journal.post', 'name' => 'ثبت قطعی سند حسابداری', 'action_type' => 'EXECUTE', 'description' => 'ثبت قطعی پیش‌نویس و انتقال مبالغ به دفتر کل و گزارش‌ها. برگشت فقط با مجوز برگشت از ثبت ممکن است؛ این مجوز حساس است.'],
            ['code' => 'finance.journal.reverse', 'name' => 'برگشت از ثبت سند حسابداری', 'action_type' => 'EXECUTE', 'description' => 'صدور سند برگشتی برای اسناد ثبت‌شده و خنثی‌کردن اثر آن‌ها در دفتر کل. برای اصلاح اشتباهات پس از ثبت قطعی استفاده می‌شود.'],
            ['code' => 'finance.period.view', 'name' => 'مشاهده دوره‌ها و وضعیت بستن', 'action_type' => 'READ', 'description' => 'مشاهده وضعیت دوره‌های مالی و چک‌لیست بستن دوره. برای برنامه‌ریزی بستن ماه/سال بدون انجام خود بستن.'],
            ['code' => 'finance.period.close', 'name' => 'بستن دوره مالی', 'action_type' => 'EXECUTE', 'description' => 'اجرای بستن دوره پس از رفع موارد مسدودکننده. پس از بستن، ثبت سند عملیاتی در آن دوره محدود می‌شود.'],
            ['code' => 'finance.period.reopen', 'name' => 'بازگشایی دوره مالی', 'action_type' => 'EXECUTE', 'description' => 'بازگشایی دوره بسته‌شده برای اصلاح اضطراری. باید با کنترل مدیریتی استفاده شود چون دوباره امکان ثبت باز می‌شود.'],
            ['code' => 'finance.report.view', 'name' => 'مشاهده گزارش‌های مالی', 'action_type' => 'READ', 'description' => 'دسترسی به تراز آزمایشی، سود و زیان، ترازنامه و گزارش‌های مشابه در محدوده شرکت‌های مجاز شما.'],
            ['code' => 'finance.treasury.view', 'name' => 'مشاهده خزانه‌داری', 'action_type' => 'READ', 'description' => 'مشاهده اسناد خزانه، صندوق، چک و وضعیت نقدینگی بدون امکان ثبت یا تأیید پرداخت.'],
            ['code' => 'finance.treasury.manage', 'name' => 'مدیریت اسناد خزانه', 'action_type' => 'EXECUTE', 'description' => 'ایجاد و ویرایش اسناد دریافت و پرداخت خزانه و چک تا مرحله قبل از ثبت نهایی.'],
            ['code' => 'finance.treasury.post', 'name' => 'ثبت نهایی اسناد خزانه', 'action_type' => 'EXECUTE', 'description' => 'ثبت نهایی اسناد خزانه و اثرگذاری روی موجودی نقد و بانک. مجوز حساس مالی است.'],
            ['code' => 'finance.ar.view', 'name' => 'مشاهده حساب‌های دریافتنی', 'action_type' => 'READ', 'description' => 'اجازه می‌دهد مطالبات از مشتریان، فاکتورهای فروش باز و وضعیت وصول را فقط مشاهده کنید. بدون آن صفحه حساب‌های دریافتنی قفل است.'],
            ['code' => 'finance.ar.manage', 'name' => 'مدیریت حساب‌های دریافتنی', 'action_type' => 'EXECUTE', 'description' => 'اجازه می‌دهد وصول مطالبات، تخصیص دریافت‌ها و تعدیل مانده مشتریان را انجام دهید. مستقیماً روی نقدینگی و گزارش فروش اعتباری اثر می‌گذارد.'],
            ['code' => 'finance.ap.view', 'name' => 'مشاهده حساب‌های پرداختنی', 'action_type' => 'READ', 'description' => 'اجازه می‌دهد فهرست بدهی‌ها به تأمین‌کنندگان، فاکتورهای خرید باز و وضعیت تسویه را فقط ببینید. امکان ثبت پرداخت یا تغییر مانده وجود ندارد.'],
            ['code' => 'finance.ap.manage', 'name' => 'مدیریت حساب‌های پرداختنی', 'action_type' => 'EXECUTE', 'description' => 'اجازه می‌دهد علاوه بر مشاهده، پرداخت‌ها و تعدیلات مربوط به بستانکاران را ثبت و پیگیری کنید. روی مانده بدهی شرکت و گزارش‌های تعهدات اثر مستقیم دارد؛ فقط به افراد مسئول تسویه با تأمین‌کننده داده شود.'],
            ['code' => 'finance.tax.view', 'name' => 'مشاهده مالیات و نرخ‌ها', 'action_type' => 'READ', 'description' => 'مشاهده نرخ‌ها و تراکنش‌های مالیاتی ثبت‌شده برای گزارش و کنترل.'],
            ['code' => 'finance.tax.manage', 'name' => 'مدیریت پیکربندی مالیات', 'action_type' => 'EXECUTE', 'description' => 'تعریف و به‌روزرسانی نرخ و بازه اعتبار مالیات. روی محاسبه مالیات اسناد بعدی اثر می‌گذارد.'],
            ['code' => 'finance.moodian.view', 'name' => 'مشاهده ارسال‌های سامانه مؤدیان', 'action_type' => 'READ', 'description' => 'مشاهده وضعیت ارسال صورتحساب‌ها به سامانه مؤدیان و پیام خطا در صورت رد.'],
            ['code' => 'finance.moodian.submit', 'name' => 'ارسال به سامانه مؤدیان', 'action_type' => 'EXECUTE', 'description' => 'ارسال یا تلاش مجدد ارسال صورتحساب به سامانه مؤدیان. مسئولیت قانونی ارسال با دارنده این مجوز است.'],
            ['code' => 'finance.compliance.view', 'name' => 'مشاهده هشدارهای انطباق', 'action_type' => 'READ', 'description' => 'مشاهده هشدارهای انطباق مالی (مثلاً ناترازی یا دوره باز) بدون رفع آن‌ها.'],
            ['code' => 'finance.compliance.manage', 'name' => 'مدیریت هشدارهای انطباق', 'action_type' => 'EXECUTE', 'description' => 'رفع، بستن یا پیگیری هشدارهای انطباق پس از اقدام اصلاحی.'],
            ['code' => 'finance.suggest.view', 'name' => 'مشاهده پیشنهادهای سند', 'action_type' => 'READ', 'description' => 'مشاهده پیشنهادهای خودکار سند که از رویدادهای عملیاتی آمده‌اند.'],
            ['code' => 'finance.suggest.manage', 'name' => 'مدیریت پیشنهادهای سند', 'action_type' => 'EXECUTE', 'description' => 'مدیریت صف پیشنهادها و قواعد تعیین حساب قبل از تصمیم نهایی.'],
            ['code' => 'finance.suggest.decide', 'name' => 'تأیید یا رد پیشنهاد سند', 'action_type' => 'EXECUTE', 'description' => 'پذیرش یا رد پیشنهاد سند؛ در صورت پذیرش ممکن است پیش‌نویس سند ساخته شود.'],
            ['code' => 'finance.fa.view', 'name' => 'مشاهده دارایی‌های ثابت', 'action_type' => 'READ', 'description' => 'مشاهده کارت دارایی ثابت، بهای تمام‌شده و استهلاک انباشته.'],
            ['code' => 'finance.fa.manage', 'name' => 'مدیریت دارایی‌های ثابت', 'action_type' => 'EXECUTE', 'description' => 'ثبت و به‌روزرسانی دارایی ثابت (خرید، انتقال، خروج).'],
            ['code' => 'finance.fa.depreciate', 'name' => 'ثبت استهلاک دارایی ثابت', 'action_type' => 'EXECUTE', 'description' => 'اجرای محاسبه و ثبت سند استهلاک دوره‌ای برای دارایی‌های مشمول.'],
            ['code' => 'finance.ic.view', 'name' => 'مشاهده عملیات بین‌شرکتی', 'action_type' => 'READ', 'description' => 'مشاهده تراکنش‌ها و وضعیت تطبیق بین شرکت‌های گروه و تراز تلفیقی.'],
            ['code' => 'finance.ic.manage', 'name' => 'مدیریت عملیات بین‌شرکتی', 'action_type' => 'EXECUTE', 'description' => 'ثبت و مدیریت اسناد و قواعد بین‌شرکتی در چارچوب دسترسی هلدینگ.'],
            ['code' => 'finance.smart.view', 'name' => 'مشاهده دستیار هوشمند مالی', 'action_type' => 'READ', 'description' => 'مشاهده پیشنهادها و بینش‌های دستیار هوشمند مالی.'],
            ['code' => 'finance.smart.decide', 'name' => 'تصمیم روی پیشنهاد دستیار هوشمند', 'action_type' => 'EXECUTE', 'description' => 'تأیید یا رد پیشنهادهای دستیار هوشمند برای اعمال در سیستم.'],
            ['code' => 'finance.smart.nl', 'name' => 'پیش‌نویس سند با زبان طبیعی', 'action_type' => 'EXECUTE', 'description' => 'ساخت پیش‌نویس سند از توضیح متنی به زبان طبیعی؛ نتیجه همچنان نیاز به بررسی و ثبت دارد.'],
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
                    'description'          => $perm['description'],
                    'status'               => 1,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        }
    }
}
