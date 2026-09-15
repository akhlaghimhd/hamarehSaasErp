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
use Throwable;

class LoadUserScopesMiddleware
{
    private const CACHE_TTL = 60;

    /** @var array<string, array|null> */
    private static array $requestMemo = [];

    public function handle(Request $request, Closure $next): Response
    {
        ScopeContext::resetInstance();

        $user = $request->user();
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (!$user || !$tenantId) {
            return $next($request);
        }

        $memoKey = $tenantId.'|'.$user->user_id;

        if (array_key_exists($memoKey, self::$requestMemo)) {
            $payload = self::$requestMemo[$memoKey];
        } else {
            $payload = $this->loadPayload($tenantId, $user->user_id);
            self::$requestMemo[$memoKey] = $payload;
        }

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

    public static function forget(string $tenantId, string $userId): void
    {
        $key = sprintf('sec_ctx:%s:%s', $tenantId, $userId);
        unset(self::$requestMemo[$tenantId.'|'.$userId]);
        try {
            if (self::cacheUsable()) {
                Cache::forget($key);
            }
        } catch (Throwable) {
            // ignore
        }
    }

    private function loadPayload(string $tenantId, string $userId): ?array
    {
        $cacheKey = sprintf('sec_ctx:%s:%s', $tenantId, $userId);

        if (self::cacheUsable()) {
            try {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }
            } catch (Throwable) {
                // fall through to DB
            }
        }

        $payload = $this->loadFromDatabase($userId, $tenantId);

        if ($payload !== null && self::cacheUsable()) {
            try {
                Cache::put($cacheKey, $payload, self::CACHE_TTL);
            } catch (Throwable) {
                // ignore
            }
        }

        return $payload;
    }

    /**
     * file cache on Docker bind-mount is pathologically slow on Win/Mac.
     * Only use redis/memcached/array for hot-path caching.
     */
    private static function cacheUsable(): bool
    {
        $store = (string) config('cache.default');

        return in_array($store, ['redis', 'memcached', 'array', 'octane'], true);
    }

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
        self::$requestMemo = [];
    }
}
