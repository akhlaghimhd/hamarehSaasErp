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

        // Short file/redis cache — avoid hitting tenants table on every API call
        $tenantOk = Cache::remember(
            'tenant_active:'.$tenantId,
            120,
            function () use ($tenantId) {
                return DB::table('tenants')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->exists();
            }
        );

        if (! $tenantOk) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive tenant.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user = $request->user()) {
            // Membership also cached briefly (status changes are rare mid-session)
            $memberKey = 'tu_active:'.$tenantId.':'.$user->user_id;
            $isMember = Cache::remember($memberKey, 60, function () use ($tenantId, $user) {
                return DB::table('tenant_users')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $user->user_id)
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->exists();
            });

            if (! $isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied or tenant is inactive.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // RLS session GUC (must run every request — cannot cache)
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        Context::add('tenant_id', $tenantId);
        app()->instance('current_tenant_id', $tenantId);
        TenantContext::getInstance()->setTenantId($tenantId);

        return $next($request);
    }

    public function terminate($request, $response): void
    {
        try {
            DB::statement("SELECT set_config('app.current_tenant_id', '', false)");
        } catch (Throwable $e) {
            // ignore aborted transaction
        }

        TenantContext::resetInstance();
    }
}
