<?php

namespace Tests\Feature\Modules\Organization;

use App\Base\Context\TenantContext;
use App\Modules\Organization\Services\OrgSalesPurchPackGuard;
use App\Modules\Organization\Services\PurchasingOrganizationService;
use App\Modules\Organization\Services\SalesOrganizationService;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * DEBT-ORG-003 / H3 — create paths require org.sales_structure / org.purch_structure.
 */
class SalesPurchFeaturePackGateTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected FeatureCatalogService $features;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'SP_PACK',
            'status'      => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);

        $this->features = app(FeatureCatalogService::class);
    }

    #[Test]
    public function sales_org_create_blocked_without_pack(): void
    {
        $this->expectException(HttpException::class);
        app(SalesOrganizationService::class)->create('SO1', 'Sales One');
    }

    #[Test]
    public function sales_org_create_allowed_with_pack(): void
    {
        $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_ORG_SALES_STRUCTURE,
            true
        );

        $row = app(SalesOrganizationService::class)->create('SO1', 'Sales One');
        $this->assertSame('SO1', $row->code);
    }

    #[Test]
    public function purch_org_create_blocked_without_pack(): void
    {
        $this->expectException(HttpException::class);
        app(PurchasingOrganizationService::class)->create('PO1', 'Purch One');
    }

    #[Test]
    public function purch_org_create_allowed_with_pack(): void
    {
        $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_ORG_PURCH_STRUCTURE,
            true
        );

        $row = app(PurchasingOrganizationService::class)->create('PO1', 'Purch One');
        $this->assertSame('PO1', $row->code);
    }

    #[Test]
    public function sales_structure_guard_blocks_without_pack(): void
    {
        $this->expectException(HttpException::class);
        OrgSalesPurchPackGuard::assertSalesStructure();
    }

    #[Test]
    public function sales_structure_guard_passes_with_pack(): void
    {
        $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_ORG_SALES_STRUCTURE,
            true
        );

        OrgSalesPurchPackGuard::assertSalesStructure();
        $this->assertTrue(
            $this->features->isEnabled(
                $this->tenant->tenant_id,
                FeatureCatalogService::CODE_ORG_SALES_STRUCTURE
            )
        );
    }
}
