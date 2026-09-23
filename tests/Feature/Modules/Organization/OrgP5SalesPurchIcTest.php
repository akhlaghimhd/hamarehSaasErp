<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\Organization\Services\CompanyService;
use App\Modules\Organization\Services\SalesOrganizationService;
use App\Modules\Organization\Services\PurchasingOrganizationService;
use App\Modules\Organization\Services\IntercompanyService;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class OrgP5SalesPurchIcTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'ORG_P5',
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
    public function sales_and_purch_org_create_and_assign(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'SP-CO',
            name: 'SP Company',
        ));

        $sales = app(SalesOrganizationService::class);
        $so = $sales->create('SO01', 'Domestic Sales', $co->company_id);
        $asg = $sales->assign($so->sales_org_id, $co->company_id);

        $this->assertSame('SO01', $so->code);
        $this->assertSame($co->company_id, $asg->company_id);

        $purch = app(PurchasingOrganizationService::class);
        $po = $purch->create('PO01', 'Central Purchasing', $co->company_id);
        $this->assertSame('PO01', $po->code);
        $this->assertCount(1, $sales->listForTenant());
        $this->assertCount(1, $purch->listForTenant());
    }

    #[Test]
    public function intercompany_partner_map_and_rule(): void
    {
        $svcCo = app(CompanyService::class);
        $a = $svcCo->createCompany(new CreateCompanyDTO(code: 'IC-A', name: 'Company A'));
        $b = $svcCo->createCompany(new CreateCompanyDTO(code: 'IC-B', name: 'Company B'));

        $ic = app(IntercompanyService::class);
        $map = $ic->mapPartners($a->company_id, $b->company_id);
        $this->assertSame($a->company_id, $map->from_company_id);

        $rule = $ic->createRule('SI-TO-PI', 'Sales inv to purch inv', 'SALES_INVOICE', 'PURCHASE_INVOICE');
        $this->assertSame('SALES_INVOICE', $rule->source_doc_type);

        $this->assertCount(1, $ic->listPartners());
        $this->assertCount(1, $ic->listRules());
    }

    #[Test]
    public function intercompany_rejects_same_company_pair(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(code: 'IC-X', name: 'X'));

        $this->expectException(\Exception::class);
        app(IntercompanyService::class)->mapPartners($co->company_id, $co->company_id);
    }
}
