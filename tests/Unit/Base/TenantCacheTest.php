<?php

namespace Tests\Unit\Base;

use Tests\TestCase;
use App\Base\Context\TenantContext;
use App\Base\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TenantCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::resetInstance();
        Cache::flush();
        parent::tearDown();
    }

    /** @test */
    public function key_follows_standard_pattern(): void
    {
        $tenantId = (string) Str::uuid();

        $key = TenantCache::key('identity', 'user_permissions:u1', $tenantId);

        $this->assertSame(
            "tenant:{$tenantId}:identity:user_permissions:u1",
            $key
        );
    }

    /** @test */
    public function key_uses_tenant_context_when_id_omitted(): void
    {
        $tenantId = (string) Str::uuid();
        TenantContext::getInstance()->setTenantId($tenantId);

        $key = TenantCache::key('organization', 'branches');

        $this->assertSame("tenant:{$tenantId}:organization:branches", $key);
    }

    /** @test */
    public function key_requires_tenant_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantCache::key('identity', 'x');
    }

    /** @test */
    public function remember_isolates_values_per_tenant(): void
    {
        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();

        TenantCache::remember('identity', 'roles', 60, fn () => ['A'], $tenantA);
        TenantCache::remember('identity', 'roles', 60, fn () => ['B'], $tenantB);

        $this->assertSame(['A'], TenantCache::get('identity', 'roles', null, $tenantA));
        $this->assertSame(['B'], TenantCache::get('identity', 'roles', null, $tenantB));
    }

    /** @test */
    public function forget_removes_only_target_tenant_key(): void
    {
        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();

        TenantCache::remember('identity', 'roles', 60, fn () => ['A'], $tenantA);
        TenantCache::remember('identity', 'roles', 60, fn () => ['B'], $tenantB);

        TenantCache::forget('identity', 'roles', $tenantA);

        $this->assertNull(TenantCache::get('identity', 'roles', null, $tenantA));
        $this->assertSame(['B'], TenantCache::get('identity', 'roles', null, $tenantB));
    }
}
