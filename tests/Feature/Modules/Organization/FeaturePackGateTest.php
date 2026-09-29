<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
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
        app(CompanyService::class)->createCompany($dto1);

        $dto2 = new CreateCompanyDTO(
            code: 'C2',
            name: 'Company Two',
            legalName: 'Company Two Legal',
            isActive: true,
            status: 1,
            isPrimary: false,
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
        app(CompanyService::class)->createCompany($dto1);

        $dto2 = new CreateCompanyDTO(
            code: 'C2',
            name: 'Company Two',
            legalName: 'Company Two Legal',
            isActive: true,
            status: 1,
            isPrimary: false,
            entityKind: 'OPERATING',
        );
        $c2 = app(CompanyService::class)->createCompany($dto2);

        $this->assertSame('C2', $c2->code);
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
}
