<?php

namespace Tests\Feature\Modules\SaasPlatform;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Models\PlatformFeatureCatalog;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FeatureCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected FeatureCatalogService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'FEAT_CAT',
            'status'      => 1,
        ]);

        $this->svc = app(FeatureCatalogService::class);
    }

    #[Test]
    public function catalog_is_seeded_with_independent_org_packs(): void
    {
        $codes = PlatformFeatureCatalog::query()->pluck('code')->all();

        $this->assertContains(FeatureCatalogService::CODE_MULTI_COMPANY, $codes);
        $this->assertContains(FeatureCatalogService::CODE_MULTI_BRANCH, $codes);
        $this->assertContains(FeatureCatalogService::CODE_MULTI_BUSINESS_UNIT, $codes);
        $this->assertContains(FeatureCatalogService::CODE_CUSTOM_ORG_HIERARCHY, $codes);
    }

    #[Test]
    public function missing_entitlement_means_disabled(): void
    {
        $this->assertFalse(
            $this->svc->isEnabled($this->tenant->tenant_id, FeatureCatalogService::CODE_MULTI_COMPANY)
        );
    }

    #[Test]
    public function enable_and_disable_entitlement(): void
    {
        $this->svc->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_BRANCH,
            true,
            'MANUAL'
        );

        $this->assertTrue(
            $this->svc->isEnabled($this->tenant->tenant_id, FeatureCatalogService::CODE_MULTI_BRANCH)
        );

        $this->svc->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_BRANCH,
            false,
            'MANUAL'
        );

        $this->assertFalse(
            $this->svc->isEnabled($this->tenant->tenant_id, FeatureCatalogService::CODE_MULTI_BRANCH)
        );
    }

    #[Test]
    public function packs_are_independent(): void
    {
        $this->svc->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_BUSINESS_UNIT,
            true
        );

        $this->assertTrue(
            $this->svc->isEnabled($this->tenant->tenant_id, FeatureCatalogService::CODE_MULTI_BUSINESS_UNIT)
        );
        $this->assertFalse(
            $this->svc->isEnabled($this->tenant->tenant_id, FeatureCatalogService::CODE_MULTI_COMPANY)
        );
        $this->assertFalse(
            $this->svc->isEnabled($this->tenant->tenant_id, FeatureCatalogService::CODE_MULTI_BRANCH)
        );
    }

    #[Test]
    public function assert_enabled_throws_when_missing(): void
    {
        $this->expectException(HttpException::class);

        $this->svc->assertEnabled(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_CUSTOM_ORG_HIERARCHY
        );
    }

    #[Test]
    public function unknown_feature_code_rejected(): void
    {
        $this->expectException(HttpException::class);

        $this->svc->setEntitlement($this->tenant->tenant_id, 'not_a_real_pack', true);
    }
}
