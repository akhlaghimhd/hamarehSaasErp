<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Services\CompanyService;
use App\Modules\Organization\Services\BusinessUnitService;
use App\Modules\Organization\Services\OrgHierarchyService;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FeaturePackGateTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected FeatureCatalogService $features;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'PACK_GATE',
            'status'      => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);

        $this->features = app(FeatureCatalogService::class);
    }

    #[Test]
    public function second_company_blocked_without_multi_company_pack(): void
    {
        $dto1 = new CreateCompanyDTO(
            code: 'C1',
            name: 'Company One',
            legalName: 'Company One Legal',
            isActive: true,
            status: 1,
            isPrimary: true,
            entityKind: 'OPERATING',
        );
        $c1 = app(CompanyService::class)->createCompany($dto1);

        $dto2 = new CreateCompanyDTO(
            code: 'C2',
            name: 'Company Two',
            legalName: 'Company Two Legal',
            isActive: true,
            status: 1,
            isPrimary: false,
            parentCompanyId: $c1->company_id,
            entityKind: 'OPERATING',
        );

        $this->expectException(HttpException::class);
        app(CompanyService::class)->createCompany($dto2);
    }

    #[Test]
    public function second_company_allowed_with_multi_company_pack(): void
    {
        $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_COMPANY,
            true
        );

        $dto1 = new CreateCompanyDTO(
            code: 'C1',
            name: 'Company One',
            legalName: 'Company One Legal',
            isActive: true,
            status: 1,
            isPrimary: true,
            entityKind: 'OPERATING',
        );
        $c1 = app(CompanyService::class)->createCompany($dto1);

        $dto2 = new CreateCompanyDTO(
            code: 'C2',
            name: 'Company Two',
            legalName: 'Company Two Legal',
            isActive: true,
            status: 1,
            isPrimary: false,
            parentCompanyId: $c1->company_id,
            entityKind: 'OPERATING',
        );
        $c2 = app(CompanyService::class)->createCompany($dto2);

        $this->assertSame('C2', $c2->code);
        $this->assertSame($c1->company_id, $c2->parent_company_id);
        $this->assertFalse((bool) $c2->is_primary);
    }

    #[Test]
    public function business_unit_blocked_without_pack(): void
    {
        $this->expectException(HttpException::class);
        app(BusinessUnitService::class)->create('BU1', 'Unit 1');
    }

    #[Test]
    public function business_unit_allowed_with_pack(): void
    {
        $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_BUSINESS_UNIT,
            true
        );

        $bu = app(BusinessUnitService::class)->create('BU1', 'Unit 1');
        $this->assertSame('BU1', $bu->code);
    }

    #[Test]
    public function custom_hierarchy_blocked_without_pack(): void
    {
        $this->expectException(HttpException::class);
        app(OrgHierarchyService::class)->createHierarchy('REP-1', 'Reporting', 'CUSTOM');
    }

    #[Test]
    public function custom_hierarchy_allowed_with_pack(): void
    {
        $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_CUSTOM_ORG_HIERARCHY,
            true
        );

        $h = app(OrgHierarchyService::class)->createHierarchy('REP-1', 'Reporting', 'CUSTOM');
        $this->assertSame('CUSTOM', $h->purpose);
    }

    #[Test]
    public function freeze_multi_company_retains_data_and_blocks_new_create(): void
    {
        $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_COMPANY,
            true
        );

        $c1 = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'C1',
            name: 'Company One',
            legalName: 'Company One Legal',
            isActive: true,
            status: 1,
            isPrimary: true,
            entityKind: 'OPERATING',
        ));

        app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'C2',
            name: 'Company Two',
            legalName: 'Company Two Legal',
            isActive: true,
            status: 1,
            isPrimary: false,
            parentCompanyId: $c1->company_id,
            entityKind: 'OPERATING',
        ));

        $this->assertSame(2, Company::where('tenant_id', $this->tenant->tenant_id)->count());

        $this->features->freezeEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_COMPANY
        );

        $this->assertFalse(
            $this->features->isEnabled($this->tenant->tenant_id, FeatureCatalogService::CODE_MULTI_COMPANY)
        );

        $this->assertSame(2, Company::where('tenant_id', $this->tenant->tenant_id)->count());

        $this->expectException(HttpException::class);
        app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'C3',
            name: 'Company Three',
            legalName: 'Company Three Legal',
            isActive: true,
            status: 1,
            isPrimary: false,
            parentCompanyId: $c1->company_id,
            entityKind: 'OPERATING',
        ));
    }

    /** PLT-W1-03 residual — unfreeze re-enables creates. */
    #[Test]
    public function unfreeze_multi_company_allows_create_again(): void
    {
        $this->features->setEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_COMPANY,
            true
        );

        $c1 = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'C1',
            name: 'Company One',
            legalName: 'Company One Legal',
            isActive: true,
            status: 1,
            isPrimary: true,
            entityKind: 'OPERATING',
        ));

        $this->features->freezeEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_COMPANY
        );

        $this->features->unfreezeEntitlement(
            $this->tenant->tenant_id,
            FeatureCatalogService::CODE_MULTI_COMPANY
        );

        $this->assertTrue(
            $this->features->isEnabled($this->tenant->tenant_id, FeatureCatalogService::CODE_MULTI_COMPANY)
        );

        $c2 = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'C2',
            name: 'Company Two',
            legalName: 'Company Two Legal',
            isActive: true,
            status: 1,
            isPrimary: false,
            parentCompanyId: $c1->company_id,
            entityKind: 'OPERATING',
        ));

        $this->assertSame('C2', $c2->code);
        $this->assertSame($c1->company_id, $c2->parent_company_id);
    }
}
