<?php

namespace App\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Context;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Symfony\Component\HttpFoundation\Response;

class LoadUserScopesMiddleware
{
    /**
     * Reload full Security Context from the database for the authenticated user.
     * Law 4.4: user_id, tenant_id, roles, scopes (+ permissions).
     *
     * F4 — Token vs Request Context:
     * This middleware is the request-time source of truth for roles/permissions/scopes.
     * Sanctum token only authenticates the user; it does not carry live authorization data.
     * Must run after TenantContextMiddleware and auth:sanctum.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (!$user || !$tenantId) {
            return $next($request);
        }

        $tenantUser = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->user_id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->first();

        if (!$tenantUser) {
            return $next($request);
        }

        // 1. Scopes (DB)
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

        // 2. Roles (DB)
        $roleRows = DB::table('tenant_user_roles')
            ->join('tenant_roles', 'tenant_user_roles.tenant_role_id', '=', 'tenant_roles.tenant_role_id')
            ->where('tenant_user_roles.tenant_id', $tenantId)
            ->where('tenant_user_roles.user_id', $user->user_id)
            ->whereNull('tenant_user_roles.deleted_at')
            ->whereNull('tenant_roles.deleted_at')
            ->where('tenant_roles.status', 1)
            ->select([
                'tenant_roles.tenant_role_id',
                'tenant_roles.code',
                'tenant_roles.name',
                'tenant_roles.is_system_default',
            ])
            ->get();

        $roles = $roleRows->map(function ($role) {
            return [
                'role_id'           => $role->tenant_role_id,
                'code'              => $role->code,
                'name'              => $role->name,
                'is_system_default' => (bool) $role->is_system_default,
            ];
        })->values()->toArray();

        $roleIds = $roleRows->pluck('tenant_role_id')->unique()->values()->toArray();

        // 3. Permissions (DB)
        $permissions = [];
        if (!empty($roleIds)) {
            $permissions = DB::table('tenant_role_permissions')
                ->join('tenant_permissions', 'tenant_role_permissions.tenant_permission_id', '=', 'tenant_permissions.tenant_permission_id')
                ->where('tenant_role_permissions.tenant_id', $tenantId)
                ->whereIn('tenant_role_permissions.tenant_role_id', $roleIds)
                ->whereNull('tenant_role_permissions.deleted_at')
                ->whereNull('tenant_permissions.deleted_at')
                ->where('tenant_permissions.status', 1)
                ->pluck('tenant_permissions.code')
                ->unique()
                ->values()
                ->toArray();
        }

        // 4. ScopeContext
        ScopeContext::getInstance()->setScopes($scopes, $tenantUser->tenant_user_id);

        // 5. Full security context (Law 4.4) — request-scoped, from DB
        $securityContext = [
            'user_id'        => $user->user_id,
            'tenant_id'      => $tenantId,
            'tenant_user_id' => $tenantUser->tenant_user_id,
            'roles'          => $roles,
            'permissions'    => $permissions,
            'scopes'         => $scopes,
            'is_owner'       => (bool) ($tenantUser->is_owner ?? false),
        ];

        Context::add('user_scopes', $scopes);
        Context::add('tenant_user_id', $tenantUser->tenant_user_id);
        Context::add('user_roles', $roles);
        Context::add('user_permissions', $permissions);
        Context::add('security_context', $securityContext);

        app()->instance('current_tenant_user_id', $tenantUser->tenant_user_id);
        app()->instance('current_user_scopes', $scopes);
        app()->instance('current_user_roles', $roles);
        app()->instance('current_user_permissions', $permissions);
        app()->instance('current_security_context', $securityContext);

        return $next($request);
    }

    public function terminate($request, $response): void
    {
        ScopeContext::resetInstance();
    }
}
