<?php

namespace App\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Context;
use App\Base\Context\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class TenantContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-ID');

        // 1. بررسی ارسال شناسه مستأجر
        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context is missing.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 2. بررسی وجود و فعال بودن مستأجر (بدون string interpolation)
        $isValidTenant = DB::table('tenants')
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->exists();

        if (! $isValidTenant) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive tenant.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 3. اعتبارسنجی عضویت کاربر در مستأجر (در صورت وجود کاربر احراز هویت‌شده)
        if ($user = $request->user()) {
            $isMember = DB::table('tenant_users')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->user_id)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->exists();

            if (! $isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied or tenant is inactive.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // 4. تنظیم امن شناسه مستأجر در سطح PostgreSQL برای RLS (parameter binding)
        // استفاده از set_config با binding برای جلوگیری از SQL Injection
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        // 5. تنظیم شناسه مستأجر در لاراول
        Context::add('tenant_id', $tenantId);
        app()->instance('current_tenant_id', $tenantId);

        // Set up TenantContext singleton
        TenantContext::getInstance()->setTenantId($tenantId);

        return $next($request);
    }

    /**
     * پاکسازی تنظیمات پس از پایان درخواست
     */
    public function terminate($request, $response): void
    {
        DB::statement("SELECT set_config('app.current_tenant_id', '', false)");
        TenantContext::resetInstance();
    }
}