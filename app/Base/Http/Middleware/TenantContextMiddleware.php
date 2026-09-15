<?php

namespace App\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Context;
use App\Base\Context\TenantContext;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TenantContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context is missing.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $tenantOk = $this->fastRemember('tenant_active:'.$tenantId, 120, function () use ($tenantId) {
            return DB::table('tenants')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->exists();
        });

        if (! $tenantOk) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive tenant.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user = $request->user()) {
            $isMember = $this->fastRemember(
                'tu_active:'.$tenantId.':'.$user->user_id,
                60,
                function () use ($tenantId, $user) {
                    return DB::table('tenant_users')
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $user->user_id)
                        ->where('status', 1)
                        ->whereNull('deleted_at')
                        ->exists();
                }
            );

            if (! $isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied or tenant is inactive.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        Context::add('tenant_id', $tenantId);
        app()->instance('current_tenant_id', $tenantId);
        TenantContext::getInstance()->setTenantId($tenantId);

        return $next($request);
    }

    /**
     * Skip file cache on Docker bind-mount (very slow on Win/Mac).
     */
    private function fastRemember(string $key, int $ttl, callable $callback): mixed
    {
        $store = (string) config('cache.default');
        $usable = in_array($store, ['redis', 'memcached', 'array', 'octane'], true);

        if (! $usable) {
            return $callback();
        }

        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (Throwable) {
            return $callback();
        }
    }

    public function terminate($request, $response): void
    {
        try {
            DB::statement("SELECT set_config('app.current_tenant_id', '', false)");
        } catch (Throwable $e) {
            // ignore
        }

        TenantContext::resetInstance();
    }
}
