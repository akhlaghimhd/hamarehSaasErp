<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\Organization\Services\CompanyService;
use App\Modules\Organization\Services\BranchService;
use App\Modules\Organization\Services\HierarchySyncService;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Modules\Organization\DTOs\CreateBranchDTO;
use App\Modules\Organization\Models\OrgHierarchy;
use App\Modules\Organization\Models\OrgHierarchyNode;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class HierarchySyncTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'HIER_SYNC',
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
    public function creating_company_creates_legal_and_establishment_system_trees(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'H1',
            name: 'Hier Co',
        ));

        $legal = OrgHierarchy::where('tenant_id', $this->tenant->tenant_id)
            ->where('code', HierarchySyncService::CODE_LEGAL)
            ->first();
        $est = OrgHierarchy::where('tenant_id', $this->tenant->tenant_id)
            ->where('code', HierarchySyncService::CODE_ESTABLISHMENT)
            ->first();

        $this->assertNotNull($legal);
        $this->assertSame(OrgHierarchy::PURPOSE_LEGAL, $legal->purpose);
        $this->assertNotNull($est);
        $this->assertSame(OrgHierarchy::PURPOSE_ESTABLISHMENT, $est->purpose);

        $this->assertTrue(
            OrgHierarchyNode::where('hierarchy_id', $legal->hierarchy_id)
                ->where('entity_type', 'COMPANY')
                ->where('entity_id', $co->company_id)
                ->exists()
        );
        $this->assertTrue(
            OrgHierarchyNode::where('hierarchy_id', $est->hierarchy_id)
                ->where('entity_type', 'COMPANY')
                ->where('entity_id', $co->company_id)
                ->exists()
        );
    }

    #[Test]
    public function creating_branch_places_node_under_company_in_establishment_tree(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'H2',
            name: 'Hier Co 2',
        ));

        $br = app(BranchService::class)->createBranch(new CreateBranchDTO(
            companyId: $co->company_id,
            code: 'BR1',
            name: 'North',
        ));

        $est = OrgHierarchy::where('tenant_id', $this->tenant->tenant_id)
            ->where('code', HierarchySyncService::CODE_ESTABLISHMENT)
            ->first();
        $this->assertNotNull($est);

        $companyNode = OrgHierarchyNode::where('hierarchy_id', $est->hierarchy_id)
            ->where('entity_type', 'COMPANY')
            ->where('entity_id', $co->company_id)
            ->first();
        $branchNode = OrgHierarchyNode::where('hierarchy_id', $est->hierarchy_id)
            ->where('entity_type', 'BRANCH')
            ->where('entity_id', $br->branch_id)
            ->first();

        $this->assertNotNull($companyNode);
        $this->assertNotNull($branchNode);
        $this->assertSame($companyNode->node_id, $branchNode->parent_node_id);
    }

    #[Test]
    public function child_company_legal_node_links_to_parent_company_node(): void
    {
        $parent = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'PAR',
            name: 'Parent Co',
            isPrimary: true,
        ));
        $child = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'CHD',
            name: 'Child Co',
            parentCompanyId: $parent->company_id,
            isPrimary: false,
        ));

        $legal = OrgHierarchy::where('tenant_id', $this->tenant->tenant_id)
            ->where('code', HierarchySyncService::CODE_LEGAL)
            ->first();
        $this->assertNotNull($legal);

        $parentNode = OrgHierarchyNode::where('hierarchy_id', $legal->hierarchy_id)
            ->where('entity_type', 'COMPANY')
            ->where('entity_id', $parent->company_id)
            ->first();
        $childNode = OrgHierarchyNode::where('hierarchy_id', $legal->hierarchy_id)
            ->where('entity_type', 'COMPANY')
            ->where('entity_id', $child->company_id)
            ->first();

        $this->assertNotNull($parentNode);
        $this->assertNotNull($childNode);
        $this->assertSame($parentNode->node_id, $childNode->parent_node_id);
    }

    #[Test]
    public function rebuild_is_idempotent(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'RB',
            name: 'Rebuild Co',
        ));

        app(HierarchySyncService::class)->rebuildSystemTreesForTenant($this->tenant->tenant_id);
        app(HierarchySyncService::class)->rebuildSystemTreesForTenant($this->tenant->tenant_id);

        $count = OrgHierarchyNode::where('tenant_id', $this->tenant->tenant_id)
            ->where('entity_type', 'COMPANY')
            ->where('entity_id', $co->company_id)
            ->count();

        // One in LEGAL + one in ESTABLISHMENT
        $this->assertSame(2, $count);
    }
}
