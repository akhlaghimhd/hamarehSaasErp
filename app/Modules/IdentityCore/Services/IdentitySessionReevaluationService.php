<?php

namespace App\Modules\IdentityCore\Services;

use App\Base\Http\Middleware\LoadUserScopesMiddleware;
use App\Base\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ID-W3-03 — Continuous access re-evaluation foundation.
 *
 * Bumps access_version on the membership and clears permission/scope caches
 * so the next request resolves fresh effective rights.
 */
class IdentitySessionReevaluationService
{
    /**
     * Invalidate caches and bump access_version for one user in a tenant.
     * Returns the new access_version (or null if membership missing).
     */
    public function invalidateUser(string $tenantId, string $userId, ?string $reason = null): ?int
    {
        $membership = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first(['tenant_user_id', 'access_version']);

        if (!$membership) {
            // Still clear caches in case of soft-deleted / partial state
            $this->clearCaches($tenantId, $userId);

            return null;
        }

        $next = ((int) ($membership->access_version ?? 1)) + 1;

        DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->update([
                'access_version' => $next,
                'updated_at'     => now(),
            ]);

        $this->clearCaches($tenantId, $userId);

        if (\Illuminate\Support\Facades\Schema::hasTable('event_outbox')) {
            DB::table('event_outbox')->insert([
                'event_id'       => (string) \Illuminate\Support\Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'tenant_users',
                'aggregate_id'   => $membership->tenant_user_id,
                'event_type'     => 'identity.access.reevaluated.v1',
                'payload'        => json_encode([
                    'user_id'        => $userId,
                    'tenant_user_id' => $membership->tenant_user_id,
                    'access_version' => $next,
                    'reason'         => $reason,
                ]),
                'status'         => 1,
                'created_at'     => now(),
            ]);
        }

        return $next;
    }

    public function currentVersion(string $tenantId, string $userId): ?int
    {
        $v = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->value('access_version');

        return $v === null ? null : (int) $v;
    }

    /**
     * True when client session version is behind server (stale rights).
     */
    public function isStale(string $tenantId, string $userId, ?int $sessionVersion): bool
    {
        if ($sessionVersion === null) {
            return false; // legacy sessions without version — not forced
        }

        $current = $this->currentVersion($tenantId, $userId);
        if ($current === null) {
            return true;
        }

        return $sessionVersion < $current;
    }

    private function clearCaches(string $tenantId, string $userId): void
    {
        try {
            TenantCache::forget('identity', "user_permissions:{$userId}", $tenantId);
        } catch (\Throwable) {
        }

        try {
            LoadUserScopesMiddleware::forget($tenantId, $userId);
        } catch (\Throwable) {
        }

        try {
            Cache::forget(sprintf('sec_ctx:%s:%s', $tenantId, $userId));
        } catch (\Throwable) {
        }
    }
}
