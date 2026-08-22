<?php

namespace App\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RequirePermission
{
    /**
     * Handle an incoming request.
     * Usage in routes: middleware('permission:sales.order.create')
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $userId = $request->user()?->user_id;

        if (!$tenantId || !$userId) {
            return response()->json(['message' => 'Unauthorized or missing context'], 401);
        }

        // استفاده از Tagged Cache لاراول جهت ایزوله‌سازی و پاک‌سازی سریع کش مستأجر
        $userPermissions = Cache::tags(["tenant:{$tenantId}"])->remember(
            "user_permissions:{$userId}", 
            now()->addHours(12), 
            function () use ($tenantId, $userId) {
                return DB::table('tenant_user_roles')
                    ->join('tenant_role_permissions', 'tenant_user_roles.tenant_role_id', '=', 'tenant_role_permissions.tenant_role_id')
                    ->join('tenant_permissions', 'tenant_role_permissions.tenant_permission_id', '=', 'tenant_permissions.tenant_permission_id')
                    ->where('tenant_user_roles.tenant_id', $tenantId)
                    ->where('tenant_user_roles.user_id', $userId)
                    ->pluck('tenant_permissions.permission_code')
                    ->toArray();
            }
        );

        if (!in_array($permission, $userPermissions)) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما مجوز دسترسی به این بخش را ندارید.'
            ], 403);
        }

        return $next($request);
    }
}