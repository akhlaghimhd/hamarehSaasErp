<?php

namespace App\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Symfony\Component\HttpFoundation\Response;

class LoadUserScopesMiddleware
{
    /**
     * بارگذاری Scopeهای کاربر احراز هویت‌شده در Context درخواست
     * این middleware باید بعد از auth:sanctum و TenantContextMiddleware اجرا شود.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = TenantContext::getInstance()->getTenantId();

        // اگر کاربر لاگین نباشد یا TenantContext تنظیم نشده باشد، چیزی بارگذاری نمی‌کنیم
        if (!$user || !$tenantId) {
            return $next($request);
        }

        // پیدا کردن tenant_user_id مربوط به این کاربر در مستأجر جاری
        $tenantUser = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->user_id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->first();

        if (!$tenantUser) {
            // کاربر عضو این مستأجر نیست (نباید به اینجا برسد چون TenantContextMiddleware چک کرده)
            return $next($request);
        }

        // بارگذاری Scopeهای فعال کاربر
        $scopes = DB::table('tenant_user_scopes')
            ->join('tenant_scopes', 'tenant_user_scopes.scope_id', '=', 'tenant_scopes.scope_id')
            ->where('tenant_user_scopes.tenant_id', $tenantId)
            ->where('tenant_user_scopes.tenant_user_id', $tenantUser->tenant_user_id)
            ->whereNull('tenant_user_scopes.deleted_at')
            ->whereNull('tenant_scopes.deleted_at')
            ->where('tenant_scopes.is_active', true)
            ->select([
                'tenant_scopes.scope_id',
                'tenant_scopes.scope_name',
                'tenant_scopes.scope_type',
                'tenant_scopes.reference_id',
                'tenant_scopes.description',
            ])
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();

        // تنظیم در ScopeContext
        ScopeContext::getInstance()->setScopes($scopes, $tenantUser->tenant_user_id);

        // همچنین در Laravel Context و app container برای دسترسی آسان‌تر
        \Illuminate\Support\Facades\Context::add('user_scopes', $scopes);
        \Illuminate\Support\Facades\Context::add('tenant_user_id', $tenantUser->tenant_user_id);
        app()->instance('current_tenant_user_id', $tenantUser->tenant_user_id);
        app()->instance('current_user_scopes', $scopes);

        return $next($request);
    }

    /**
     * پاکسازی پس از پایان درخواست
     */
    public function terminate($request, $response): void
    {
        ScopeContext::resetInstance();
    }
}