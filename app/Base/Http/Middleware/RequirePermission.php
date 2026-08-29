<?php

namespace App\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Base\Context\TenantContext;
use App\Base\Support\TenantCache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Handle an incoming request.
     * Usage in routes: middleware('permission:identity.role.create')
     *
     * F4: Permission codes are always resolved from DB (cache is derived only).
     * F7: Cache key = tenant:{tenant_id}:identity:user_permissions:{user_id}
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $userId = $request->user()?->user_id;

        if (!$tenantId || !$userId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized or missing tenant/user context.',
            ], 401);
        }

        $userPermissions = TenantCache::remember(
            'identity',
            "user_permissions:{$userId}",
            now()->addHours(12),
            function () use ($tenantId, $userId) {
                return DB::table('tenant_user_roles')
                    ->join(
                        'tenant_role_permissions',
                        'tenant_user_roles.tenant_role_id',
                        '=',
                        'tenant_role_permissions.tenant_role_id'
                    )
                    ->join(
                        'tenant_permissions',
                        'tenant_role_permissions.tenant_permission_id',
                        '=',
                        'tenant_permissions.tenant_permission_id'
                    )
                    ->where('tenant_user_roles.tenant_id', $tenantId)
                    ->where('tenant_user_roles.user_id', $userId)
                    ->whereNull('tenant_user_roles.deleted_at')
                    ->whereNull('tenant_role_permissions.deleted_at')
                    ->whereNull('tenant_permissions.deleted_at')
                    ->where('tenant_permissions.status', 1)
                    ->pluck('tenant_permissions.code')
                    ->unique()
                    ->values()
                    ->toArray();
            },
            $tenantId
        );

        if (!in_array($permission, $userPermissions, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'شما مجوز دسترسی به این بخش را ندارید.',
            ], 403);
        }

        return $next($request);
    }
}
