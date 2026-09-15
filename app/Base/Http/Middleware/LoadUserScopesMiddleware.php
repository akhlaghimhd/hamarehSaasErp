<?php

namespace App\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Context;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Symfony\Component\HttpFoundation\Response;

class LoadUserScopesMiddleware
{
    /** Seconds to cache roles/permissions/scopes per tenant-user (request burst relief). */
    private const CACHE_TTL = 60;

    /**
     * Reload Security Context from DB (or short cache) for the authenticated user.
     * Law 4.4: user_id, tenant_id, roles, scopes (+ permissions).
     */
    public function handle(Request $request, Closure $next): Response
    {
        ScopeContext::resetInstance();

        $user = $request->user();
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (!$user || !$tenantId) {
            return $next($request);
        }

        $cacheKey = sprintf(
            'sec_ctx:%s:%s',
            $tenantId,
            $user->user_id
        );

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $tenantId) {
            return $this->loadFromDatabase($user->user_id, $tenantId);
        });

        if ($payload === null) {
            return $next($request);
        }

        $scopes = $payload['scopes'];
        $roles = $payload['roles'];
        $permissions = $payload['permissions'];
        $tenantUserId = $payload['tenant_user_id'];
        $isOwner = $payload['is_owner'];

        ScopeContext::getInstance()->setScopes($scopes, $tenantUserId);

        $securityContext = [
            'user_id'        => $user->user_id,
            'tenant_id'      => $tenantId,
            'tenant_user_id' => $tenantUserId,
            'roles'          => $roles,
            'permissions'    => $permissions,
            'scopes'         => $scopes,
            'is_owner'       => $isOwner,
        ];

        Context::add('user_scopes', $scopes);
        Context::add('tenant_user_id', $tenantUserId);
        Context::add('user_roles', $roles);
        Context::add('user_permissions', $permissions);
        Context::add('security_context', $securityContext);

        app()->instance('current_tenant_user_id', $tenantUserId);
        app()->instance('current_user_scopes', $scopes);
        app()->instance('current_user_roles', $roles);
        app()->instance('current_user_permissions', $permissions);
        app()->instance('current_security_context', $securityContext);

        return $next($request);
    }

    /**
     * Invalidate cached security context (call after role/permission/scope assignment).
     */
    public static function forget(string $tenantId, string $userId): void
    {
        Cache::forget(sprintf('sec_ctx:%s:%s', $tenantId, $userId));
    }

    /**
     * @return array{tenant_user_id:string,is_owner:bool,scopes:array,roles:array,permissions:array}|null
     */
    private function loadFromDatabase(string $userId, string $tenantId): ?array
    {
        $tenantUser = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->first();

        if (!$tenantUser) {
            return null;
        }

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
            ->map(function ($item) {
                $row = (array) $item;
                if (isset($row['scope_type'])) {
                    $row['scope_type'] = strtoupper((string) $row['scope_type']);
                }

                return $row;
            })
            ->toArray();

        $roleRows = DB::table('tenant_user_roles')
            ->join('tenant_roles', 'tenant_user_roles.tenant_role_id', '=', 'tenant_roles.tenant_role_id')
            ->where('tenant_user_roles.tenant_id', $tenantId)
            ->where('tenant_user_roles.user_id', $userId)
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

        return [
            'tenant_user_id' => $tenantUser->tenant_user_id,
            'is_owner'       => (bool) ($tenantUser->is_owner ?? false),
            'scopes'         => $scopes,
            'roles'          => $roles,
            'permissions'    => $permissions,
        ];
    }

    public function terminate($request, $response): void
    {
        ScopeContext::resetInstance();
    }
}
