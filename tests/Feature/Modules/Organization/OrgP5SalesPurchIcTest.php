<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\Organization\Services\CompanyService;
use App\Modules\Organization\Services\SalesOrganizationService;
use App\Modules\Organization\Services\PurchasingOrganizationService;
use App\Modules\Organization\Services\SalesStructureService;
use App\Modules\Organization\Services\IntercompanyService;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use App\Base\Exceptions\DomainException;
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
    public function sales_structure_channel_division_area_and_office(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'SP-ST',
            name: 'Structure Co',
        ));

        $so = app(SalesOrganizationService::class)->create('SO-DOM', 'Domestic', $co->company_id);
        $struct = app(SalesStructureService::class);

        $ch = $struct->createChannel('RETAIL', 'Retail');
        $this->assertSame('RETAIL', $ch->code);

        $div = $struct->createDivision('FG', 'Finished Goods');
        $this->assertSame('FG', $div->code);

        $area = $struct->createSalesArea(
            $so->sales_org_id,
            $ch->distribution_channel_id,
            $div->division_id,
            'SO-DOM-RETAIL-FG',
            'Domestic Retail FG'
        );
        $this->assertSame($so->sales_org_id, $area->sales_org_id);
        $this->assertCount(1, $struct->listSalesAreas());

        $office = $struct->createOffice('TEH-01', 'Tehran Office', $so->sales_org_id);
        $group = $struct->createGroup($office->sales_office_id, 'G1', 'Team North');
        $this->assertSame('G1', $group->code);
        $this->assertCount(1, $struct->listOffices());

        $struct->softDeleteSalesArea($area->sales_area_id);
        $this->assertCount(0, $struct->listSalesAreas());

        $struct->softDeleteChannel($ch->distribution_channel_id);
        $struct->softDeleteDivision($div->division_id);
        $this->assertCount(0, $struct->listChannels());
        $this->assertCount(0, $struct->listDivisions());
    }

    #[Test]
    public function sales_area_rejects_duplicate_triple(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(code: 'SP-DUP', name: 'Dup Co'));
        $so = app(SalesOrganizationService::class)->create('SO-X', 'X', $co->company_id);
        $struct = app(SalesStructureService::class);
        $ch = $struct->createChannel('CH1', 'Channel 1');
        $div = $struct->createDivision('D1', 'Div 1');

        $struct->createSalesArea($so->sales_org_id, $ch->distribution_channel_id, $div->division_id);

        $this->expectException(DomainException::class);
        $struct->createSalesArea($so->sales_org_id, $ch->distribution_channel_id, $div->division_id);
    }

    #[Test]
    public function channel_code_must_be_unique_per_tenant(): void
    {
        $struct = app(SalesStructureService::class);
        $struct->createChannel('DUP', 'One');

        $this->expectException(DomainException::class);
        $struct->createChannel('DUP', 'Two');
    }

    #[Test]
    public function cannot_delete_channel_or_division_while_sales_area_references_them(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'SP-REF',
            name: 'Ref Co',
        ));
        $so = app(SalesOrganizationService::class)->create('SO-R', 'R', $co->company_id);
        $struct = app(SalesStructureService::class);
        $ch = $struct->createChannel('CH-R', 'Channel R');
        $div = $struct->createDivision('DIV-R', 'Div R');
        $struct->createSalesArea(
            $so->sales_org_id,
            $ch->distribution_channel_id,
            $div->division_id
        );

        try {
            $struct->softDeleteChannel($ch->distribution_channel_id);
            $this->fail('Expected DomainException for channel in use');
        } catch (DomainException $e) {
            $this->assertSame('channel_in_use_by_sales_area', $e->errorCode);
        }

        try {
            $struct->softDeleteDivision($div->division_id);
            $this->fail('Expected DomainException for division in use');
        } catch (DomainException $e) {
            $this->assertSame('division_in_use_by_sales_area', $e->errorCode);
        }

        $this->assertCount(1, $struct->listChannels());
        $this->assertCount(1, $struct->listDivisions());
        $this->assertCount(1, $struct->listSalesAreas());
    }

    #[Test]
    public function structure_entities_support_update_and_restore(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'SP-UR',
            name: 'Update Restore Co',
        ));
        $so = app(SalesOrganizationService::class)->create('SO-UR', 'UR Sales', $co->company_id);
        $struct = app(SalesStructureService::class);

        $ch = $struct->createChannel('CH-UR', 'Channel UR');
        $ch = $struct->updateChannel($ch->distribution_channel_id, 'CH-UR2', 'Channel UR2', true);
        $this->assertSame('CH-UR2', $ch->code);

        $div = $struct->createDivision('DIV-UR', 'Div UR');
        $div = $struct->updateDivision($div->division_id, 'DIV-UR2', 'Div UR2', true);
        $this->assertSame('DIV-UR2', $div->code);

        $area = $struct->createSalesArea(
            $so->sales_org_id,
            $ch->distribution_channel_id,
            $div->division_id,
            'AREA-1',
            'Area One'
        );
        $area = $struct->updateSalesArea($area->sales_area_id, 'AREA-2', 'Area Two', true);
        $this->assertSame('AREA-2', $area->code);

        $office = $struct->createOffice('OFF-1', 'Office 1', $so->sales_org_id);
        $office = $struct->updateOffice($office->sales_office_id, 'OFF-2', 'Office 2', $so->sales_org_id, true);
        $this->assertSame('OFF-2', $office->code);

        $group = $struct->createGroup($office->sales_office_id, 'G-1', 'Group 1');
        $group = $struct->updateGroup($group->sales_group_id, 'G-2', 'Group 2', true);
        $this->assertSame('G-2', $group->code);

        $struct->softDeleteSalesArea($area->sales_area_id);
        $struct->softDeleteChannel($ch->distribution_channel_id);
        $struct->softDeleteDivision($div->division_id);
        $struct->softDeleteGroup($group->sales_group_id);
        $struct->softDeleteOffice($office->sales_office_id);

        $this->assertCount(0, $struct->listChannels());
        $this->assertCount(0, $struct->listDivisions());
        $this->assertCount(0, $struct->listSalesAreas());
        $this->assertCount(0, $struct->listOffices());

        $struct->restoreChannel($ch->distribution_channel_id);
        $struct->restoreDivision($div->division_id);
        $struct->restoreSalesArea($area->sales_area_id);
        $struct->restoreOffice($office->sales_office_id);
        $struct->restoreGroup($group->sales_group_id);

        $this->assertCount(1, $struct->listChannels());
        $this->assertCount(1, $struct->listDivisions());
        $this->assertCount(1, $struct->listSalesAreas());
        $this->assertCount(1, $struct->listOffices());
        $this->assertFalse((bool) $struct->listChannels()->first()->is_active);
    }

    #[Test]
    public function office_and_group_codes_must_be_unique(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(code: 'SP-UQ', name: 'UQ Co'));
        $so = app(SalesOrganizationService::class)->create('SO-UQ', 'UQ', $co->company_id);
        $struct = app(SalesStructureService::class);

        $struct->createOffice('OFF-X', 'Office X', $so->sales_org_id);
        try {
            $struct->createOffice('OFF-X', 'Office X2', $so->sales_org_id);
            $this->fail('Expected DomainException for duplicate office code');
        } catch (DomainException $e) {
            $this->assertSame('duplicate_office_code', $e->errorCode);
        }

        $office = $struct->createOffice('OFF-Y', 'Office Y', $so->sales_org_id);
        $struct->createGroup($office->sales_office_id, 'GX', 'Group X');
        try {
            $struct->createGroup($office->sales_office_id, 'GX', 'Group X2');
            $this->fail('Expected DomainException for duplicate group code');
        } catch (DomainException $e) {
            $this->assertSame('duplicate_group_code', $e->errorCode);
        }
    }

    #[Test]
    public function deleting_office_cascades_soft_delete_to_groups(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(code: 'SP-CAS', name: 'Cascade Co'));
        $so = app(SalesOrganizationService::class)->create('SO-CAS', 'CAS', $co->company_id);
        $struct = app(SalesStructureService::class);

        $office = $struct->createOffice('OFF-CAS', 'Cascade Office', $so->sales_org_id);
        $g1 = $struct->createGroup($office->sales_office_id, 'G-A', 'Team A');
        $g2 = $struct->createGroup($office->sales_office_id, 'G-B', 'Team B');

        $struct->softDeleteOffice($office->sales_office_id);

        $this->assertCount(0, $struct->listOffices());
        $this->assertTrue(
            \App\Modules\Organization\Models\SalesGroup::onlyTrashed()
                ->where('sales_group_id', $g1->sales_group_id)
                ->exists()
        );
        $this->assertTrue(
            \App\Modules\Organization\Models\SalesGroup::onlyTrashed()
                ->where('sales_group_id', $g2->sales_group_id)
                ->exists()
        );
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

        $rule = $ic->createRule('SO-TO-PO', 'Sales order to purchase order', 'SO', 'PO');
        $this->assertSame('SO', $rule->source_doc_type);
        $this->assertSame('PO', $rule->target_doc_type);

        $listed = $ic->listPartners();
        $this->assertCount(1, $listed);
        $this->assertSame('Company A', $listed->first()['from_company_name']);
        $this->assertCount(1, $ic->listRules());
    }

    #[Test]
    public function intercompany_rejects_same_company_pair(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(code: 'IC-X', name: 'X'));

        $this->expectException(\Exception::class);
        app(IntercompanyService::class)->mapPartners($co->company_id, $co->company_id);
    }

    #[Test]
    public function intercompany_rejects_unknown_document_type(): void
    {
        $ic = app(IntercompanyService::class);

        $this->expectException(\Exception::class);
        $ic->createRule('BAD', 'Bad rule', 'SALES_INVOICE', 'PO');
    }

    #[Test]
    public function intercompany_partner_update_deactivate_and_soft_delete(): void
    {
        $svcCo = app(CompanyService::class);
        $a = $svcCo->createCompany(new CreateCompanyDTO(code: 'IC-U1', name: 'U1'));
        $b = $svcCo->createCompany(new CreateCompanyDTO(code: 'IC-U2', name: 'U2'));

        $ic = app(IntercompanyService::class);
        $map = $ic->mapPartners($a->company_id, $b->company_id, notes: 'initial');

        $updated = $ic->updatePartner($map->ic_partner_id, [
            'notes' => 'updated',
            'is_active' => false,
        ]);
        $this->assertFalse((bool) $updated->is_active);
        $this->assertSame('updated', $updated->notes);

        $ic->softDeletePartner($map->ic_partner_id);
        $this->assertCount(0, $ic->listPartners());
    }

    #[Test]
    public function intercompany_rule_update_and_soft_delete(): void
    {
        $ic = app(IntercompanyService::class);
        $rule = $ic->createRule('INV-BILL', 'Invoice to bill', 'INV', 'BILL', true);

        $updated = $ic->updateRule($rule->ic_rule_id, [
            'auto_create_mirror' => false,
            'is_active' => false,
        ]);
        $this->assertFalse((bool) $updated->auto_create_mirror);
        $this->assertFalse((bool) $updated->is_active);

        $ic->softDeleteRule($rule->ic_rule_id);
        $this->assertCount(0, $ic->listRules());
    }

    #[Test]
    public function intercompany_document_type_catalog_is_non_empty(): void
    {
        $catalog = app(IntercompanyService::class)->documentTypeCatalog();
        $this->assertNotEmpty($catalog);
        $codes = array_column($catalog, 'code');
        $this->assertContains('SO', $codes);
        $this->assertContains('PO', $codes);
        $this->assertContains('INV', $codes);
        $this->assertContains('BILL', $codes);
    }
}
