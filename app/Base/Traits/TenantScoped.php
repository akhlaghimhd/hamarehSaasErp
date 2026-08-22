<?php

namespace App\Base\Traits;

use App\Base\Context\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

trait TenantScoped
{
    /**
     * Boot the tenant scoped trait for a model.
     */
    protected static function bootTenantScoped(): void
    {
        // 1. فیلتر اتوماتیک در تمام کوئری‌های Select / Update / Delete
        static::addGlobalScope('tenant_isolation', function (Builder $builder) {
            $tenantId = self::getCurrentTenantId();

            if ($tenantId) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        // 2. تزریق اتوماتیک tenant_id زمان Create شدن مدل‌های جدید
        static::creating(function (Model $model) {
            $tenantId = self::getCurrentTenantId();

            if ($tenantId && empty($model->tenant_id)) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    /**
     * Single Source of Truth برای شناسه مستأجر فعلی.
     * اولویت:
     * 1) TenantContext (singleton)
     * 2) Laravel Context facade
     * 3) app container (current_tenant_id)
     */
    public static function getCurrentTenantId(): ?string
    {
        $fromTenantContext = TenantContext::getInstance()->getTenantId();
        if (!empty($fromTenantContext)) {
            return $fromTenantContext;
        }

        $fromContext = Context::get('tenant_id');
        if (!empty($fromContext)) {
            return $fromContext;
        }

        if (app()->bound('current_tenant_id')) {
            $fromApp = app('current_tenant_id');
            if (!empty($fromApp)) {
                return $fromApp;
            }
        }

        return null;
    }
}