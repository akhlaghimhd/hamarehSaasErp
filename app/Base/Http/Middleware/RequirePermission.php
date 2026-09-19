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
     * Policy:
     * - Tenant owner (is_owner on active membership) always passes — does not depend on
     *   tenant_permissions rows existing or on a cached role→permission map.
     * - Everyone else is checked against role-assigned permission codes (cached).
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

        // Owner check is never served from the permission-list cache (avoids stale empty lists).
        if ($this->isActiveTenantOwner($tenantId, $userId)) {
            return $next($request);
        }

        $userPermissions = TenantCache::remember(
            'identity',
            "user_permissions:{$userId}",
            now()->addHours(12),
            function () use ($tenantId, $userId) {
                return $this->resolveRolePermissionCodes($tenantId, $userId);
            },
            $tenantId
        );

        if (!is_array($userPermissions) || !in_array($permission, $userPermissions, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'شما مجوز دسترسی به این بخش را ندارید.',
            ], 403);
        }

        return $next($request);
    }

    private function isActiveTenantOwner(string $tenantId, string $userId): bool
    {
        return (bool) DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where('is_owner', true)
            ->exists();
    }

    /**
     * @return list<string>
     */
    private function resolveRolePermissionCodes(string $tenantId, string $userId): array
    {
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
    }
}
