<?php

namespace App\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Base\Context\TenantContext;
use App\Base\Support\TenantCache;
use App\Modules\IdentityCore\Services\RoleInheritanceService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Handle an incoming request.
     * Usage in routes: middleware('permission:identity.role.create')
     *
     * Policy:
     * - Tenant owner (is_owner on active membership) always passes.
     * - Everyone else is checked against role-assigned permission codes
     *   including inherited permissions from parent roles (ID-W2-03).
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
        $roleIds = DB::table('tenant_user_roles')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('tenant_role_id')
            ->unique()
            ->values()
            ->all();

        return app(RoleInheritanceService::class)->permissionCodesForRoles($tenantId, $roleIds);
    }
}
