<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enriches tenant_permissions.description with operational Persian text
 * so role-assign UI shows what granting/denying each permission actually does.
 * Safe to re-run.
 */
class EnrichPermissionDescriptionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('tenant_permissions')) {
            return;
        }

        foreach ($this->opsCatalog() as $code => $description) {
            DB::table('tenant_permissions')
                ->where('code', $code)
                ->update([
                    'description' => $description,
                    'updated_at' => now(),
                ]);
        }
    }

    /** @return array<string, string> */
    private function opsCatalog(): array
    {
        return [
            'identity.user.view' => 'اجازه می‌دهد فهرست اعضای سازمان و جزئیات عضویت را ببیند. بدون آن صفحه کاربران سازمان باز نمی‌شود.',
            'identity.user.create' => 'اجازه می‌دهد عضو جدید به سازمان اضافه کند (موبایل و اطلاعات عضویت). بدون آن دکمه افزودن کاربر غیرفعال/مسدود است.',
            'identity.user.update' => 'اجازه می‌دهد وضعیت یا اطلاعات عضو را ویرایش کند (مثلاً فعال/غیرفعال). بدون آن فقط مشاهده است.',
            'identity.user.delete' => 'اجازه می‌دهد عضویت را حذف نرم کند (کاربر از سازمان خارج می‌شود، داده پاک فیزیکی نمی‌شود). خطرناک — فقط به افراد مورد اعتماد بدهید.',
            'identity.user.restore' => 'اجازه می‌دهد عضو حذف‌شده را دوباره فعال کند.',
            'identity.role.view' => 'اجازه می‌دهد فهرست نقش‌ها و جزئیات هر نقش را ببیند.',
            'identity.role.create' => 'اجازه می‌دهد نقش شغلی جدید تعریف کند (مثلاً حسابدار شعبه).',
            'identity.role.update' => 'اجازه می‌دهد نام، توضیح یا ساختار نقش را تغییر دهد.',
            'identity.role.delete' => 'اجازه می‌دهد نقش را حذف نرم کند. اگر کاربری به آن وصل باشد ممکن است محدود شود.',
            'identity.role.assign' => 'اجازه می‌دهد نقش را مستقیماً به یک عضو بدهد یا از او بگیرد. این همان «چه شغل/دسترسی‌ای دارد» است.',
            'identity.role.assign-permissions' => 'اجازه می‌دهد مشخص کند هر نقش کدام مجوزها را دارد. حساس‌ترین مجوز مدیریت دسترسی است.',
            'identity.role.manage' => 'دسترسی یکپارچه به مدیریت نقش‌ها (ایجاد/ویرایش/حذف در یک بسته).',
            'identity.permission.view' => 'اجازه می‌دهد کاتالوگ مجوزهای سیستم را ببیند (معمولاً فقط مشاهده؛ ساخت مجوز از UI محدود است).',
            'identity.permission.create' => 'اجازه ثبت مجوز جدید در کاتالوگ (عمدتاً برای توسعه/سیدر؛ به کاربران عادی ندهید).',
            'identity.permission.update' => 'اجازه ویرایش نام/توضیح مجوز در کاتالوگ.',
            'identity.permission.delete' => 'اجازه حذف مجوز از کاتالوگ (بسیار حساس).',
            'identity.scope.view' => 'اجازه می‌دهد محدوده‌های دسترسی (شرکت/شعبه/BU و …) را ببیند.',
            'identity.scope.create' => 'اجازه می‌دهد محدوده جدید تعریف کند (مثلاً «فقط شعبه شمال»).',
            'identity.scope.update' => 'اجازه ویرایش تعریف محدوده.',
            'identity.scope.delete' => 'اجازه حذف محدوده.',
            'identity.scope.assign' => 'اجازه می‌دهد محدوده را به عضو بچسباند تا فقط داده‌های همان محدوده را ببیند/تغییر دهد.',
            'identity.profile.view' => 'اجازه مشاهده پروفایل (معمولاً پروفایل خود کاربر).',
            'identity.profile.update' => 'اجازه ویرایش پروفایل (نام، تماس، آواتار و …).',
            'identity.profile.delete' => 'اجازه حذف پروفایل (نادر؛ معمولاً لازم نیست).',
            'identity.membership_history.view' => 'اجازه می‌دهد تاریخچه ورود/خروج/تغییر وضعیت اعضا را ببیند (حسابرسی عضویت).',
            'identity.sod.view' => 'اجازه می‌دهد قوانین تعارض نقش (تفکیک وظایف) را ببیند. مثلاً ببیند کدام دو نقش با هم ممنوع‌اند.',
            'identity.sod.manage' => 'اجازه می‌دهد قانون تعارض بسازد/حذف کند. با داشتن این مجوز می‌تواند سیاست امنیتی سازمان را عوض کند.',
            'identity.mfa.manage' => 'اجازه مدیریت تنظیمات MFA در سطح ادمین. خود کاربر معمولاً MFA خودش را از پروفایل کنترل می‌کند.',
            'identity.access_cert.view' => 'اجازه می‌دهد کمپین‌های بازبینی دسترسی و وضعیت آن‌ها را ببیند. بدون آن صفحه بازبینی دسترسی باز نمی‌شود.',
            'identity.access_cert.manage' => 'اجازه می‌دهد کمپین بازبینی بسازد، باز کند و ببندد. مدیر امنیت/منابع انسانی معمولاً این را دارد.',
            'identity.access_cert.certify' => 'اجازه تصمیم روی هر مورد بازبینی: تأیید ادامه دسترسی، درخواست لغو نقش، یا موکول کردن. بدون این مجوز فقط می‌تواند لیست را ببیند و نمی‌تواند رأی بدهد.',
            'identity.privileged.view' => 'اجازه می‌دهد درخواست‌ها و گرنت‌های دسترسی اضطراری (زمان‌دار) را ببیند.',
            'identity.privileged.request' => 'اجازه می‌دهد برای خودش یا دیگری درخواست نقش حساس با مدت محدود ثبت کند (break-glass).',
            'identity.privileged.approve' => 'اجازه می‌دهد درخواست اضطراری را تأیید، رد یا زودتر لغو کند و نقش ممتاز را علامت‌گذاری کند. بسیار حساس.',
            'organization.company.view' => 'اجازه مشاهده فهرست و جزئیات شرکت‌های سازمان.',
            'organization.company.create' => 'اجازه ثبت شرکت جدید (اگر بسته multi_company فعال باشد).',
            'organization.company.update' => 'اجازه ویرایش اطلاعات شرکت (نام حقوقی، وضعیت، فیلدهای ثبتی و …).',
            'organization.company.delete' => 'اجازه حذف نرم شرکت.',
            'organization.branch.view' => 'اجازه مشاهده شعب.',
            'organization.branch.create' => 'اجازه ایجاد شعبه جدید (با محدودیت بسته multi_branch).',
            'organization.branch.update' => 'اجازه ویرایش شعبه.',
            'organization.branch.delete' => 'اجازه حذف نرم شعبه.',
            'organization.department.view' => 'اجازه مشاهده واحدهای سازمانی/دپارتمان‌ها.',
            'organization.department.create' => 'اجازه ایجاد واحد سازمانی.',
            'organization.department.update' => 'اجازه ویرایش واحد سازمانی.',
            'organization.department.delete' => 'اجازه حذف نرم واحد سازمانی.',
            'organization.ownership.view' => 'اجازه مشاهده درصد مالکیت بین شرکت‌های گروه.',
            'organization.ownership.manage' => 'اجازه ثبت/تغییر درصد مالکیت شرکت‌ها.',
            'organization.fiscal.view' => 'اجازه مشاهده انتساب دوره مالی به شرکت.',
            'organization.fiscal.manage' => 'اجازه تنظیم دوره مالی شرکت.',
            'organization.bank.view' => 'اجازه مشاهده حساب‌های بانکی تعریف‌شده برای شرکت.',
            'organization.bank.manage' => 'اجازه افزودن/ویرایش حساب بانکی شرکت.',
            'organization.officer.view' => 'اجازه مشاهده مقامات/اعضای هیئت‌مدیره ثبت‌شده برای شرکت.',
            'organization.officer.manage' => 'اجازه مدیریت مقامات شرکت.',
            'organization.business_unit.view' => 'اجازه مشاهده واحدهای کسب‌وکار (BU).',
            'organization.business_unit.manage' => 'اجازه ایجاد/ویرایش BU (نیاز به بسته multi_business_unit).',
            'organization.cost_center.view' => 'اجازه مشاهده مراکز هزینه.',
            'organization.cost_center.manage' => 'اجازه مدیریت مراکز هزینه.',
            'organization.hierarchy.view' => 'اجازه مشاهده درخت‌های سلسله‌مراتب سازمانی.',
            'organization.hierarchy.manage' => 'اجازه ساخت/ویرایش درخت سلسله‌مراتب (CUSTOM نیاز به بسته مربوطه دارد).',
            'organization.sales_org.view' => 'اجازه مشاهده سازمان فروش.',
            'organization.sales_org.manage' => 'اجازه مدیریت سازمان فروش.',
            'organization.purch_org.view' => 'اجازه مشاهده سازمان خرید.',
            'organization.purch_org.manage' => 'اجازه مدیریت سازمان خرید.',
            'organization.sales_structure.view' => 'اجازه مشاهده کانال/دیویژن/ناحیه فروش.',
            'organization.sales_structure.manage' => 'اجازه مدیریت ساختار فروش.',
            'organization.intercompany.view' => 'اجازه مشاهده شرکای بین‌شرکتی و قوانین گروه.',
            'organization.intercompany.manage' => 'اجازه تعریف شریک IC و قوانین تبادل داخل گروه.',
            'organization.consolidation.view' => 'اجازه مشاهده اجرای تلفیق (وقتی ماژول حسابداری آماده باشد).',
            'organization.consolidation.manage' => 'اجازه مدیریت اجرای تلفیق.',
            'organization.structure.configure' => 'اجازه پیکربندی قالب ساختار سازمانی (ESC).',
            'partner.partner.view' => 'اجازه مشاهده فهرست طرف‌های تجاری (نماینده، شریک کانال و …).',
            'partner.partner.create' => 'اجازه ثبت طرف تجاری جدید.',
            'partner.partner.update' => 'اجازه ویرایش اطلاعات طرف تجاری.',
            'partner.partner.delete' => 'اجازه حذف نرم طرف تجاری.',
            'partner.partner_user.view' => 'اجازه مشاهده کاربران وابسته به طرف تجاری.',
            'partner.partner_user.create' => 'اجازه ایجاد کاربر برای پرتال/دسترسی طرف تجاری.',
            'partner.partner_user.update' => 'اجازه ویرایش کاربر طرف تجاری.',
            'partner.partner_user.delete' => 'اجازه حذف کاربر طرف تجاری.',
            'partner.assignment.view' => 'اجازه مشاهده اینکه طرف تجاری به کدام سازمان/شرکت وصل است.',
            'partner.assignment.create' => 'اجازه تخصیص طرف تجاری به سازمان.',
            'partner.assignment.update' => 'اجازه ویرایش تخصیص سازمان.',
            'partner.assignment.delete' => 'اجازه حذف تخصیص سازمان.',
            'partner.agreement.view' => 'اجازه مشاهده توافق‌نامه‌های همکاری.',
            'partner.agreement.create' => 'اجازه ثبت توافق‌نامه جدید.',
            'partner.agreement.update' => 'اجازه ویرایش توافق‌نامه.',
            'partner.agreement.delete' => 'اجازه حذف توافق‌نامه.',
            'partner.commission_rule.view' => 'اجازه مشاهده قواعد محاسبه کمیسیون.',
            'partner.commission_rule.create' => 'اجازه تعریف قاعده کمیسیون.',
            'partner.commission_rule.update' => 'اجازه ویرایش قاعده کمیسیون.',
            'partner.commission_rule.delete' => 'اجازه حذف قاعده کمیسیون.',
            'partner.commission.view' => 'اجازه مشاهده کمیسیون‌های محاسبه‌شده.',
            'partner.commission.create' => 'اجازه ثبت رکورد کمیسیون.',
            'partner.commission.update' => 'اجازه ویرایش کمیسیون.',
            'partner.commission.delete' => 'اجازه حذف کمیسیون.',
            'partner.payout.view' => 'اجازه مشاهده تسویه‌های پرداخت به شریک.',
            'partner.payout.create' => 'اجازه ثبت تسویه.',
            'partner.payout.update' => 'اجازه ویرایش تسویه.',
            'partner.payout.delete' => 'اجازه حذف تسویه.',
            'partner.contact.view' => 'اجازه مشاهده مخاطبین طرف تجاری.',
            'partner.contact.create' => 'اجازه افزودن مخاطب.',
            'partner.contact.update' => 'اجازه ویرایش مخاطب.',
            'partner.contact.delete' => 'اجازه حذف مخاطب.',
            'partner.document.view' => 'اجازه مشاهده اسناد پیوست شریک.',
            'partner.document.create' => 'اجازه آپلود/ثبت سند.',
            'partner.document.update' => 'اجازه ویرایش سند.',
            'partner.document.delete' => 'اجازه حذف سند.',
            'partner.bank_account.view' => 'اجازه مشاهده حساب بانکی شریک.',
            'partner.bank_account.create' => 'اجازه ثبت حساب بانکی شریک.',
            'partner.bank_account.update' => 'اجازه ویرایش حساب بانکی شریک.',
            'partner.bank_account.delete' => 'اجازه حذف حساب بانکی شریک.',
            'partner.activity_log.view' => 'اجازه مشاهده لاگ فعالیت‌های مربوط به شریک.',
            'partner.activity_log.create' => 'اجازه ثبت رویداد در لاگ فعالیت.',
        ];
    }
}
