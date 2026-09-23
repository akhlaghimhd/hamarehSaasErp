<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Services\CompanyService;
use App\Modules\Organization\Services\BranchService;
use App\Modules\Organization\Services\CompanyBankAccountService;
use App\Modules\Organization\Services\CompanyOfficerService;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Modules\Organization\DTOs\CreateBranchDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class OrgP3BankOfficersBranchTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'ORG_P3',
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

    private function company(): Company
    {
        return app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'P3-CO',
            name: 'P3 Company',
        ));
    }

    #[Test]
    public function bank_account_create_and_list(): void
    {
        $co = $this->company();
        $svc = app(CompanyBankAccountService::class);

        $row = $svc->create([
            'company_id'     => $co->company_id,
            'bank_name'      => 'Melli',
            'account_number' => '123456',
            'is_primary'     => true,
        ]);

        $this->assertTrue((bool) $row->is_primary);
        $this->assertCount(1, $svc->listForCompany($co->company_id));
    }

    #[Test]
    public function officer_create_and_list(): void
    {
        $co = $this->company();
        $svc = app(CompanyOfficerService::class);

        $row = $svc->create([
            'company_id'            => $co->company_id,
            'role_code'             => 'CEO',
            'full_name'             => 'Ali Rezaei',
            'has_signing_authority' => true,
        ]);

        $this->assertSame('CEO', $row->role_code);
        $this->assertCount(1, $svc->listForCompany($co->company_id));
    }

    #[Test]
    public function branch_plant_grade_fields_persisted(): void
    {
        $co = $this->company();
        $wh = (string) Str::uuid();

        $branch = app(BranchService::class)->createBranch(new CreateBranchDTO(
            companyId: $co->company_id,
            code: 'PLANT-1',
            name: 'Plant North',
            branchKind: 'PLANT',
            defaultWarehouseId: $wh,
            supportsShipping: true,
            supportsReceiving: true,
            isManufacturingSite: true,
        ));

        $this->assertSame('PLANT', $branch->branch_kind);
        $this->assertSame($wh, $branch->default_warehouse_id);
        $this->assertTrue((bool) $branch->is_manufacturing_site);
    }

    #[Test]
    public function branch_parent_same_company_and_no_cycle(): void
    {
        $co = $this->company();
        $svc = app(BranchService::class);

        $parent = $svc->createBranch(new CreateBranchDTO(
            companyId: $co->company_id,
            code: 'HQ-BR',
            name: 'HQ Branch',
            branchKind: 'OFFICE',
        ));

        $child = $svc->createBranch(new CreateBranchDTO(
            companyId: $co->company_id,
            code: 'SUB-BR',
            name: 'Sub Branch',
            parentBranchId: $parent->branch_id,
        ));

        $this->assertSame($parent->branch_id, $child->parent_branch_id);

        $this->expectException(\Exception::class);
        // cycle: make parent point to child
        $svc->updateBranch($parent->branch_id, new \App\Modules\Organization\DTOs\UpdateBranchDTO(
            code: $parent->code,
            name: $parent->name,
            parentBranchId: $child->branch_id,
            parentBranchIdProvided: true,
        ));
    }
}
