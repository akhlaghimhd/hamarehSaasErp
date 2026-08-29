<?php

namespace App\Base\Support;

use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Tenant-aware cache keys per Isolation Architecture Standard §7 / F7.
 *
 * Pattern: tenant:{tenant_id}:{module}:{key}
 */
class TenantCache
{
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

    public static function get(string $module, string $key, mixed $default = null, ?string $tenantId = null): mixed
    {
        $tenantId = $tenantId ?? TenantContext::getInstance()->getTenantId();
        $cacheKey = self::key($module, $key, $tenantId);

        try {
            return Cache::tags(["tenant:{$tenantId}"])->get($cacheKey, $default);
        } catch (\BadMethodCallException $e) {
            return Cache::get($cacheKey, $default);
        }
    }

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
            return Cache::remember($cacheKey, $ttl, $callback);
        }
    }

    public static function forget(string $module, string $key, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantContext::getInstance()->getTenantId();
        $cacheKey = self::key($module, $key, $tenantId);

        try {
            return (bool) Cache::tags(["tenant:{$tenantId}"])->forget($cacheKey);
        } catch (\BadMethodCallException $e) {
            return (bool) Cache::forget($cacheKey);
        }
    }
}
