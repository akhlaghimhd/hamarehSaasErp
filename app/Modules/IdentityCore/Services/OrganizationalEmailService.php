<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\User;
use App\Modules\SaasAdmin\Models\SystemSetting;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Models\TenantDomain;
use App\Modules\SaasPlatform\Models\TenantSetting;
use Exception;

/**
 * Resolves organizational email host and builds unique emails.
 *
 * Host rules:
 * - White-label: primary tenant domain when primary_domain_enabled
 * - Shared platform: {email_domain_suffix}.{platform_email_base_domain}
 */
class OrganizationalEmailService
{
    public const SETTING_EMAIL_DOMAIN_SUFFIX = 'email_domain_suffix';
    public const SETTING_PLATFORM_EMAIL_BASE = 'platform_email_base_domain';
    public const DEFAULT_PLATFORM_EMAIL_BASE = 'erp.ir';

    /**
     * @throws Exception when host cannot be resolved
     */
    public function resolveEmailHost(string $tenantId): string
    {
        $tenant = Tenant::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        if (!$tenant) {
            throw new Exception('سازمان یافت نشد.');
        }

        if ((bool) $tenant->primary_domain_enabled) {
            $primary = TenantDomain::query()
                ->where('tenant_id', $tenantId)
                ->where('is_primary', true)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->orderByDesc('updated_at')
                ->first();

            if ($primary && filled($primary->domain_name)) {
                return $this->normalizeHost((string) $primary->domain_name);
            }
        }

        $suffix = TenantSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('setting_key', self::SETTING_EMAIL_DOMAIN_SUFFIX)
            ->whereNull('deleted_at')
            ->value('setting_value');

        $suffix = is_string($suffix) ? strtolower(trim($suffix)) : '';
        $suffix = preg_replace('/[^a-z0-9-]/', '', $suffix) ?? '';

        if ($suffix === '') {
            throw new Exception(
                'دامنه ایمیل سازمانی تنظیم نشده است. ابتدا پسوند ایمیل سازمان را یک‌بار تنظیم کنید یا دامنه اختصاصی فعال کنید.'
            );
        }

        $base = SystemSetting::getValue(
            self::SETTING_PLATFORM_EMAIL_BASE,
            self::DEFAULT_PLATFORM_EMAIL_BASE
        );
        $base = $this->normalizeHost((string) $base);

        if ($base === '') {
            throw new Exception('دامنه عمومی پلتفرم برای ایمیل تنظیم نشده است.');
        }

        return $suffix.'.'.$base;
    }

    /**
     * Build email from admin-chosen local part + org host. Rejects if taken.
     *
     * @throws Exception
     */
    public function buildEmailFromLocalPart(string $tenantId, string $localPart): string
    {
        $host = $this->resolveEmailHost($tenantId);
        $local = $this->sanitizeLocalPart($localPart);

        if ($local === '') {
            throw new Exception('بخش ابتدایی ایمیل معتبر نیست.');
        }

        $email = $local.'@'.$host;

        if ($this->emailExists($email)) {
            throw new Exception('این آدرس ایمیل قبلاً ثبت شده است. بخش ابتدایی را تغییر دهید.');
        }

        return $email;
    }

    /**
     * Generate unique email: first.last[@seq]@host (server-side fallback).
     *
     * @throws Exception
     */
    public function generateUniqueEmail(string $tenantId, string $firstName, string $lastName): string
    {
        $host = $this->resolveEmailHost($tenantId);
        $local = $this->buildLocalPart($firstName, $lastName);

        $candidate = $local.'@'.$host;
        $seq = 1;

        while ($this->emailExists($candidate)) {
            $seq++;
            $candidate = $local.'.'.$seq.'@'.$host;
            if ($seq > 9999) {
                throw new Exception('امکان تولید ایمیل یکتا برای این نام وجود ندارد.');
            }
        }

        return $candidate;
    }

    public function buildLocalPart(string $firstName, string $lastName): string
    {
        $first = $this->slugPart($firstName);
        $last = $this->slugPart($lastName);

        if ($first === '' && $last === '') {
            return 'user';
        }
        if ($first === '') {
            return $last;
        }
        if ($last === '') {
            return $first;
        }

        return $first.'.'.$last;
    }

    public function sanitizeLocalPart(string $localPart): string
    {
        $value = strtolower(trim($localPart));
        $value = $this->transliterateFa($value);
        $value = preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
        $value = trim($value, '.-_');
        $value = preg_replace('/\.{2,}/', '.', $value) ?? $value;

        return $value;
    }

    private function emailExists(string $email): bool
    {
        return User::query()
            ->where('email', $email)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = rtrim($host, '/');
        $host = explode('/', $host)[0] ?? $host;

        return $host;
    }

    private function slugPart(string $value): string
    {
        $value = trim($value);
        $value = $this->transliterateFa($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '.', $value) ?? '';
        $value = trim($value, '.');

        return $value;
    }

    private function transliterateFa(string $text): string
    {
        $map = [
            'آ' => 'a', 'ا' => 'a', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
            'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z',
            'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's',
            'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f',
            'ق' => 'gh', 'ک' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
            'و' => 'v', 'ه' => 'h', 'ی' => 'y', 'ي' => 'y', 'ء' => '', 'ٔ' => '',
            'ه‌' => 'eh', 'ة' => 'h',
        ];

        return strtr($text, $map);
    }
}
