<?php

namespace App\Base\Traits;

use App\Base\Context\ScopeContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * ScopeScoped Trait
 *
 * اعمال فیلتر سطح Resource بر اساس Scopeهای کاربر جاری.
 * مکمل TenantScoped است و زنجیره دسترسی قانون ۴.۲ را کامل می‌کند:
 * User → Role → Permission → Scope → Resource
 *
 * نحوه استفاده در مدل:
 *
 *   class Branch extends Model
 *   {
 *       use TenantScoped, ScopeScoped;
 *
 *       // نوع Scope و نام ستون مرجع در جدول
 *       protected static string $scopeType = 'BRANCH';
 *       protected static string $scopeColumn = 'branch_id';
 *   }
 *
 * رفتار:
 * - اگر کاربر برای scopeType مورد نظر حداقل یک Scope داشته باشد → فقط reference_idهای مجاز فیلتر می‌شوند.
 * - اگر کاربر هیچ Scope از آن نوع نداشته باشد → فیلتر اضافی اعمال نمی‌شود (فقط Tenant Isolation باقی می‌ماند).
 *   این رفتار امکان استقرار تدریجی را بدون شکستن دسترسی‌های فعلی فراهم می‌کند.
 * - در محیط بدون ScopeContext (مثل Artisan/Queue بدون context) فیلتر Scope اعمال نمی‌شود.
 */
trait ScopeScoped
{
    /**
     * Boot the scope scoped trait for a model.
     */
    protected static function bootScopeScoped(): void
    {
        static::addGlobalScope('scope_isolation', function (Builder $builder) {
            $scopeType = static::getScopeType();
            $scopeColumn = static::getScopeColumn();

            if (empty($scopeType) || empty($scopeColumn)) {
                return;
            }

            $allowedReferenceIds = static::getAllowedReferenceIds($scopeType);

            // اگر کاربر برای این نوع Scope هیچ محدوده‌ای ندارد → فیلتر اضافه نکن
            // (Tenant Isolation از TenantScoped همچنان فعال است)
            if ($allowedReferenceIds === null) {
                return;
            }

            // اگر لیست خالی باشد (کاربر Scope دارد ولی reference_id ندارد) → هیچ رکوردی برنگردان
            if (empty($allowedReferenceIds)) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $table = $builder->getModel()->getTable();
            $builder->whereIn($table . '.' . $scopeColumn, $allowedReferenceIds);
        });
    }

    /**
     * نوع Scope مربوط به این مدل (مثلاً BRANCH, WAREHOUSE, COMPANY)
     * مدل باید این مقدار را تعریف کند.
     */
    protected static function getScopeType(): ?string
    {
        return property_exists(static::class, 'scopeType')
            ? static::$scopeType
            : null;
    }

    /**
     * نام ستون مرجع در جدول مدل که باید فیلتر شود
     * (معمولاً primary key مدل یا ستون مرتبط مثل company_id)
     */
    protected static function getScopeColumn(): ?string
    {
        if (property_exists(static::class, 'scopeColumn')) {
            return static::$scopeColumn;
        }

        // fallback: اگر primary key مشخص باشد از آن استفاده کن
        $model = new static();
        return $model->getKeyName();
    }

    /**
     * دریافت reference_idهای مجاز کاربر برای یک scopeType
     *
     * @return array|null  null = هیچ Scope از این نوع تعریف نشده (فیلتر نکن)
     *                     array = لیست reference_idهای مجاز (حتی اگر خالی)
     */
    protected static function getAllowedReferenceIds(string $scopeType): ?array
    {
        $scopeContext = ScopeContext::getInstance();

        // اگر ScopeContext خالی است (مثلاً درخواست بدون load.scopes) → فیلتر نکن
        if (!$scopeContext->hasScopes()) {
            // تلاش از Laravel Context / Container
            $scopesFromContext = Context::get('user_scopes');
            if (empty($scopesFromContext) && app()->bound('current_user_scopes')) {
                $scopesFromContext = app('current_user_scopes');
            }

            if (empty($scopesFromContext) || !is_array($scopesFromContext)) {
                return null;
            }

            // پر کردن موقت ScopeContext از Context برای یکنواختی
            $scopeContext->setScopes($scopesFromContext);
        }

        $scopesOfType = $scopeContext->getScopesByType($scopeType);

        // کاربر هیچ Scope از این نوع ندارد → null برگردان (فیلتر اضافی اعمال نشود)
        if (empty($scopesOfType)) {
            return null;
        }

        return $scopeContext->getReferenceIdsByType($scopeType);
    }

    /**
     * Helper: آیا کاربر جاری به یک reference_id خاص دسترسی دارد؟
     */
    public static function currentUserHasAccessTo(string $referenceId): bool
    {
        $scopeType = static::getScopeType();
        if (empty($scopeType)) {
            return true;
        }

        $allowed = static::getAllowedReferenceIds($scopeType);

        // null یعنی محدودیتی تعریف نشده
        if ($allowed === null) {
            return true;
        }

        return in_array($referenceId, $allowed, true);
    }

    /**
     * غیرفعال کردن موقت فیلتر Scope برای یک Query خاص
     * (مشابه withoutGlobalScope)
     */
    public static function withoutScopeIsolation(): Builder
    {
        return static::withoutGlobalScope('scope_isolation');
    }
}