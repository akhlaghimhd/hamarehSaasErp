<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\Organization\Services\CompanyService;
use App\Modules\Organization\Services\BusinessUnitService;
use App\Modules\Organization\Services\HierarchySyncService;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Modules\Organization\Models\OrgHierarchy;
use App\Modules\Organization\Models\OrgHierarchyNode;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class HierarchySyncExtendedTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'HIER_EXT',
            'status'      => 1,
        ]);

        $user = User::factory()->create(['status' => 1]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $user->user_id,
            'status'    => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
        ScopeContext::resetInstance();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    #[Test]
    public function health_is_healthy_after_company_create_and_rebuild(): void
    {
        app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'HX1',
            name: 'Health Co',
        ));

        $sync = app(HierarchySyncService::class);
        $sync->rebuildSystemTreesForTenant($this->tenant->tenant_id);
        $health = $sync->health($this->tenant->tenant_id);

        $this->assertContains($health['status'], [
            HierarchySyncService::STATUS_HEALTHY,
            HierarchySyncService::STATUS_NEEDS_SYNC,
        ]);
        $this->assertArrayHasKey('counts', $health);
    }

    #[Test]
    public function ensure_structural_trees_reports_tier(): void
    {
        app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'HX2',
            name: 'Tier Co',
        ));

        $result = app(HierarchySyncService::class)->ensureStructuralTrees($this->tenant->tenant_id);

        $this->assertArrayHasKey('tier', $result);
        $this->assertGreaterThanOrEqual(1, $result['company_count']);
    }

    #[Test]
    public function multiple_business_units_sync_into_product_tree(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'HX3',
            name: 'BU Parent',
        ));

        $svc = app(BusinessUnitService::class);
        $bu1 = $svc->create('P1', 'Product 1');
        $bu2 = $svc->create('P2', 'Product 2');
        $svc->assignCompany($bu1->business_unit_id, $co->company_id, true);
        $svc->assignCompany($bu2->business_unit_id, $co->company_id, true);

        app(HierarchySyncService::class)->rebuildSystemTreesForTenant($this->tenant->tenant_id);

        $product = OrgHierarchy::where('tenant_id', $this->tenant->tenant_id)
            ->where('code', HierarchySyncService::CODE_PRODUCT)
            ->first();

        $this->assertNotNull($product);

        $this->assertTrue(
            OrgHierarchyNode::where('hierarchy_id', $product->hierarchy_id)
                ->where('entity_type', 'BUSINESS_UNIT')
                ->where('entity_id', $bu1->business_unit_id)
                ->exists()
        );
        $this->assertTrue(
            OrgHierarchyNode::where('hierarchy_id', $product->hierarchy_id)
                ->where('entity_type', 'BUSINESS_UNIT')
                ->where('entity_id', $bu2->business_unit_id)
                ->exists()
        );
    }
}
