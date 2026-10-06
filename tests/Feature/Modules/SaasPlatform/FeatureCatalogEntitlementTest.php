<?php

namespace Tests\Feature\Modules\SaasPlatform;

use App\Modules\SaasPlatform\Events\TenantFeatureGrantedV1;
use App\Modules\SaasPlatform\Events\TenantFeatureRevokedV1;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureCatalogEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected FeatureCatalogService $features;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'FEAT_ENT',
            'status'      => 1,
        ]);

        $this->features = app(FeatureCatalogService::class);
    }

    #[Test]
    public function default_without_entitlement_is_disabled(): void
    {
        $this->assertFalse(
            $this->features->isEnabled(
                $this->tenant->tenant_id,
                FeatureCatalogService::CODE_MULTI_COMPANY
            )
        );

        $this->assertSame([], $this->features->enabledCodesForTenant($this->tenant->tenant_id));
    }

    #[Test]
    public function grant_then_revoke_toggles_is_enabled_and_writes_outbox(): void
    {
        $row = $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_BRANCH,
            true
        );

        $this->assertTrue((bool) $row->is_enabled);
        $this->assertTrue(
            $this->features->isEnabled(
                $this->tenant->tenant_id,
                FeatureCatalogService::CODE_MULTI_BRANCH
            )
        );

        $granted = DB::table('event_outbox')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('event_type', TenantFeatureGrantedV1::EVENT_TYPE)
            ->where('aggregate_id', $row->entitlement_id)
            ->first();

        $this->assertNotNull($granted);
        $payload = json_decode($granted->payload, true);
        $this->assertSame(FeatureCatalogService::CODE_MULTI_BRANCH, $payload['feature_code']);
        $this->assertTrue($payload['is_enabled']);

        $revokedRow = $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_BRANCH,
            false
        );

        $this->assertFalse((bool) $revokedRow->is_enabled);
        $this->assertFalse(
            $this->features->isEnabled(
                $this->tenant->tenant_id,
                FeatureCatalogService::CODE_MULTI_BRANCH
            )
        );

        $revoked = DB::table('event_outbox')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('event_type', TenantFeatureRevokedV1::EVENT_TYPE)
            ->where('aggregate_id', $revokedRow->entitlement_id)
            ->first();

        $this->assertNotNull($revoked);
    }

    #[Test]
    public function noop_regrant_does_not_duplicate_outbox(): void
    {
        $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_COMPANY,
            true
        );

        $before = DB::table('event_outbox')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('event_type', TenantFeatureGrantedV1::EVENT_TYPE)
            ->count();

        $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_COMPANY,
            true
        );

        $after = DB::table('event_outbox')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('event_type', TenantFeatureGrantedV1::EVENT_TYPE)
            ->count();

        $this->assertSame($before, $after);
    }

    #[Test]
    public function catalog_includes_locked_org_packs_including_intercompany(): void
    {
        $codes = $this->features->listCatalog(true)->pluck('code')->all();

        foreach ([
            FeatureCatalogService::CODE_MULTI_COMPANY,
            FeatureCatalogService::CODE_MULTI_BRANCH,
            FeatureCatalogService::CODE_MULTI_BUSINESS_UNIT,
            FeatureCatalogService::CODE_CUSTOM_ORG_HIERARCHY,
            FeatureCatalogService::CODE_ORG_INTERCOMPANY,
        ] as $code) {
            $this->assertContains($code, $codes, "Missing pack code: {$code}");
        }
    }
}
