<?php

namespace Tests\Feature\Modules\Organization;

use App\Modules\Organization\Contracts\HierarchyReportContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HierarchyReportContractTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private HierarchyReportContract $svc;

    private string $rootEntity;

    private string $childEntity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'HRC1',
            'tenant_name' => 'Hierarchy Report Tenant',
            'slug'        => 'hrc-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);

        $hierarchyId = (string) Str::uuid();
        $this->rootEntity = (string) Str::uuid();
        $this->childEntity = (string) Str::uuid();
        $rootNode = (string) Str::uuid();
        $childNode = (string) Str::uuid();

        DB::table('erp_org_hierarchies')->insert([
            'hierarchy_id' => $hierarchyId,
            'tenant_id'    => $this->tenantId,
            'code'         => 'LEGAL-R',
            'name'         => 'Legal',
            'purpose'      => 'LEGAL',
            'version'      => 1,
            'is_active'    => true,
            'row_version'  => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('erp_org_hierarchy_nodes')->insert([
            [
                'node_id'        => $rootNode,
                'tenant_id'      => $this->tenantId,
                'hierarchy_id'   => $hierarchyId,
                'parent_node_id' => null,
                'entity_type'    => 'COMPANY',
                'entity_id'      => $this->rootEntity,
                'node_origin'    => 'MANUAL',
                'sort_order'     => 1,
                'is_active'      => true,
                'row_version'    => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'node_id'        => $childNode,
                'tenant_id'      => $this->tenantId,
                'hierarchy_id'   => $hierarchyId,
                'parent_node_id' => $rootNode,
                'entity_type'    => 'COMPANY',
                'entity_id'      => $this->childEntity,
                'node_origin'    => 'MANUAL',
                'sort_order'     => 2,
                'is_active'      => true,
                'row_version'    => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);

        $this->svc = app(HierarchyReportContract::class);
    }

    #[Test]
    public function active_hierarchy_returns_header(): void
    {
        $h = $this->svc->activeHierarchy($this->tenantId, 'LEGAL');
        $this->assertNotNull($h);
        $this->assertSame('LEGAL', $h['purpose']);
        $this->assertSame('LEGAL-R', $h['code']);
    }

    #[Test]
    public function nodes_by_purpose_returns_flat_list(): void
    {
        $nodes = $this->svc->nodesByPurpose($this->tenantId, 'LEGAL');
        $this->assertCount(2, $nodes);
    }

    #[Test]
    public function tree_by_purpose_nests_children(): void
    {
        $tree = $this->svc->treeByPurpose($this->tenantId, 'LEGAL');
        $this->assertCount(1, $tree);
        $this->assertSame($this->rootEntity, $tree[0]['entity_id']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame($this->childEntity, $tree[0]['children'][0]['entity_id']);
    }

    #[Test]
    public function descendant_entity_ids_include_children(): void
    {
        $ids = $this->svc->descendantEntityIds(
            $this->tenantId,
            'LEGAL',
            'COMPANY',
            $this->rootEntity
        );

        $this->assertContains($this->rootEntity, $ids);
        $this->assertContains($this->childEntity, $ids);
    }

    #[Test]
    public function missing_purpose_returns_empty(): void
    {
        $this->assertNull($this->svc->activeHierarchy($this->tenantId, 'TAX'));
        $this->assertSame([], $this->svc->nodesByPurpose($this->tenantId, 'TAX'));
        $this->assertSame([], $this->svc->treeByPurpose($this->tenantId, 'TAX'));
    }
}
