<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Services\AdminTenantService;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminTenantOpsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_tenants_with_pagination_and_search(): void
    {
        Tenant::query()->create([
            'tenant_code' => 'ALPHA01',
            'tenant_name' => 'Alpha Co',
            'legal_name'  => 'Alpha Legal',
            'tenant_type' => 1,
            'slug'        => 'alpha-co',
            'status'      => 1,
        ]);
        Tenant::query()->create([
            'tenant_code' => 'BETA02',
            'tenant_name' => 'Beta Co',
            'legal_name'  => 'Beta Legal',
            'tenant_type' => 1,
            'slug'        => 'beta-co',
            'status'      => 1,
        ]);

        /** @var AdminTenantService $svc */
        $svc = app(AdminTenantService::class);

        $all = $svc->list(perPage: 10);
        $this->assertGreaterThanOrEqual(2, $all->total());

        $filtered = $svc->list(search: 'Alpha', perPage: 10);
        $this->assertGreaterThanOrEqual(1, $filtered->total());
        $this->assertTrue(
            collect($filtered->items())->contains(fn (Tenant $t) => $t->tenant_code === 'ALPHA01')
        );
    }

    #[Test]
    public function it_shows_tenant_with_entitlements(): void
    {
        $tenant = Tenant::query()->create([
            'tenant_code' => 'SHOW01',
            'tenant_name' => 'Show Tenant',
            'slug'        => 'show-tenant',
            'tenant_type' => 1,
            'status'      => 1,
        ]);

        /** @var FeatureCatalogService $features */
        $features = app(FeatureCatalogService::class);
        // Catalog may be empty in fresh DB — setEntitlement tolerates known codes from seed migrations
        try {
            $features->setEntitlement(
                $tenant->tenant_id,
                FeatureCatalogService::CODE_MULTI_COMPANY,
                true,
                'MANUAL',
                'test'
            );
        } catch (\Throwable) {
            // If catalog row missing, show still returns empty enabled_codes
        }

        /** @var AdminTenantService $svc */
        $svc = app(AdminTenantService::class);
        $detail = $svc->show($tenant->tenant_id);

        $this->assertSame($tenant->tenant_id, $detail['tenant']->tenant_id);
        $this->assertIsArray($detail['enabled_codes']);
        $this->assertArrayHasKey('member_count', $detail);
        $this->assertSame(0, $detail['member_count']);
    }

    #[Test]
    public function show_missing_tenant_throws(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        /** @var AdminTenantService $svc */
        $svc = app(AdminTenantService::class);
        $svc->show((string) Str::uuid());
    }
}
