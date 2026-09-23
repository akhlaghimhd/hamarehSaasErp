<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\Organization\Services\CompanyService;
use App\Modules\Organization\Services\BusinessUnitService;
use App\Modules\Organization\Services\CostCenterService;
use App\Modules\Organization\Services\OrgHierarchyService;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Modules\Organization\Models\OrgHierarchy;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class OrgP4BuCostHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'ORG_P4',
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
    public function business_unit_create_and_assign_company(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'BU-CO',
            name: 'BU Company',
        ));

        $svc = app(BusinessUnitService::class);
        $bu = $svc->create('BU01', 'Operations BU');
        $asg = $svc->assignCompany($bu->business_unit_id, $co->company_id, true);

        $this->assertTrue((bool) $asg->is_primary);
        $this->assertCount(1, $svc->listForTenant());
    }

    #[Test]
    public function cost_center_create_under_company(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'CC-CO',
            name: 'CC Company',
        ));

        $svc = app(CostCenterService::class);
        $cc = $svc->create([
            'company_id' => $co->company_id,
            'code'       => 'CC-100',
            'name'       => 'Admin',
        ]);

        $this->assertSame('CC-100', $cc->code);
        $this->assertCount(1, $svc->listForCompany($co->company_id));
    }

    #[Test]
    public function hierarchy_legal_and_management_with_nodes(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'H-CO',
            name: 'Hier Company',
        ));

        $svc = app(OrgHierarchyService::class);

        $legal = $svc->createHierarchy('LEGAL-V1', 'Legal Structure', OrgHierarchy::PURPOSE_LEGAL);
        $mgmt = $svc->createHierarchy('MGMT-V1', 'Management Structure', OrgHierarchy::PURPOSE_MANAGEMENT);

        $this->assertSame('LEGAL', $legal->purpose);
        $this->assertSame('MANAGEMENT', $mgmt->purpose);

        $node = $svc->addNode($legal->hierarchy_id, 'COMPANY', $co->company_id);
        $this->assertSame('COMPANY', $node->entity_type);

        $list = $svc->listHierarchies();
        $this->assertGreaterThanOrEqual(2, $list->count());
    }
}
