<?php

namespace App\Base\Support;

use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Tenant-aware cache keys per Isolation Architecture Standard §7 / F7.
 *
 * Pattern: tenant:{tenant_id}:{module}:{key}
 * Example: tenant:550e8400-e29b-41d4-a716-446655440000:identity:user_permissions:abc
 */
class TenantCache
{
    /**
     * Build a fully-qualified tenant cache key.
     *
     * @param  string  $module  Logical module (e.g. identity, organization, master_data)
     * @param  string  $key     Key segment(s) without tenant prefix
     * @param  string|null  $tenantId  Defaults to current TenantContext
     */
    public static function key(string $module, string $key, ?string $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantContext::getInstance()->getTenantId();

        if (!$tenantId) {
            throw new InvalidArgumentException('TenantCache requires a tenant_id (context or explicit).');
        }

        $module = strtolower(trim($module, ' :'));
        $key = trim($key, ' :');

        if ($module === '' || $key === '') {
            throw new InvalidArgumentException('TenantCache module and key must be non-empty.');
        }

        return "tenant:{$tenantId}:{$module}:{$key}";
    }

    /**
     * Remember a value under the standard tenant key.
     * Uses tenant tag when the store supports tags (for bulk invalidation).
     */
    public static function remember(
        string $module,
        string $key,
        mixed $ttl,
        callable $callback,
        ?string $tenantId = null
    ): mixed {
        $tenantId = $tenantId ?? TenantContext::getInstance()->getTenantId();
        $cacheKey = self::key($module, $key, $tenantId);

        try {
            return Cache::tags(["tenant:{$tenantId}"])->remember($cacheKey, $ttl, $callback);
        } catch (\BadMethodCallException $e) {
            // Store without tag support (e.g. file, some array configs)
            return Cache::remember($cacheKey, $ttl, $callback);
        }
    }

    /**
     * Forget a tenant-scoped key.
     */
    public static function forget(string $module, string $key, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantContext::getInstance()->getTenantId();
        $cacheKey = self::key($module, $key, $tenantId);

        try {
            return Cache::tags(["tenant:{$tenantId}"])->forget($cacheKey);
        } catch (\BadMethodCallException $e) {
            return Cache::forget($cacheKey);
        }
    }
}
