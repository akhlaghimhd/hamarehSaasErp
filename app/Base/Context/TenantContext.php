<?php

namespace App\Base\Context;

use Illuminate\Support\Facades\DB;

class TenantContext
{
    protected static ?TenantContext $instance = null;
    protected ?string $tenantId = null;

    /**
     * Get the singleton instance of TenantContext
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful for testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * تنظیم شناسه مستأجر در کانَتکست فعلی و اعمال آن در سطح دیتابیس (RLS)
     */
    public function setTenantId(?string $tenantId): self
    {
        $this->tenantId = $tenantId;
        
        // جلوگیری از SQL Injection با Binding پارامتر
        if ($tenantId) {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);
        } else {
            // ریست کردن کانَتکست برای جلوگیری از Data Bleed در Worker ها و اتصالات دیگر
            DB::statement("SELECT set_config('app.current_tenant_id', '', false)");
        }

        return $this;
    }

    /**
     * دریافت شناسه مستأجر
     */
    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    /**
     * بررسی وجود کانَتکست
     */
    public function hasTenant(): bool
    {
        return !empty($this->tenantId);
    }
}
