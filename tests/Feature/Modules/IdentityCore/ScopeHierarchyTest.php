<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Services\ScopeHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ScopeHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private ScopeHierarchyService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'SCH1',
            'tenant_name' => 'Scope Hierarchy Tenant',
            'slug'        => 'sch-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);

        $this->svc = app(ScopeHierarchyService::class);
    }

    #[Test]
    public function invalid_purpose_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->svc->assertValidPurpose('NOT_A_REAL_PURPOSE');
    }

    #[Test]
    public function without_subtree_returns_only_reference(): void
    {
        $ref = (string) Str::uuid();

        $ids = $this->svc->expandReference(
            $this->tenantId,
            'COMPANY',
            $ref,
            'LEGAL',
            false
        );

        $this->assertSame([$ref], $ids);
    }

    #[Test]
    public function with_subtree_includes_descendant_entities(): void
    {
        $hierarchyId = (string) Str::uuid();
        $companyRoot = (string) Str::uuid();
        $companyChild = (string) Str::uuid();
        $companyGrand = (string) Str::uuid();

        DB::table('erp_org_hierarchies')->insert([
            'hierarchy_id' => $hierarchyId,
            'tenant_id'    => $this->tenantId,
            'code'         => 'LEGAL-1',
            'name'         => 'Legal Tree',
            'purpose'      => 'LEGAL',
            'version'      => 1,
            'is_active'    => true,
            'row_version'  => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $rootNodeId = (string) Str::uuid();
        $childNodeId = (string) Str::uuid();
        $grandNodeId = (string) Str::uuid();

        DB::table('erp_org_hierarchy_nodes')->insert([
            [
                'node_id'        => $rootNodeId,
                'tenant_id'      => $this->tenantId,
                'hierarchy_id'   => $hierarchyId,
                'parent_node_id' => null,
                'entity_type'    => 'COMPANY',
                'entity_id'      => $companyRoot,
                'node_origin'    => 'MANUAL',
                'sort_order'     => 1,
                'is_active'      => true,
                'row_version'    => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'node_id'        => $childNodeId,
                'tenant_id'      => $this->tenantId,
                'hierarchy_id'   => $hierarchyId,
                'parent_node_id' => $rootNodeId,
                'entity_type'    => 'COMPANY',
                'entity_id'      => $companyChild,
                'node_origin'    => 'MANUAL',
                'sort_order'     => 2,
                'is_active'      => true,
                'row_version'    => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'node_id'        => $grandNodeId,
                'tenant_id'      => $this->tenantId,
                'hierarchy_id'   => $hierarchyId,
                'parent_node_id' => $childNodeId,
                'entity_type'    => 'COMPANY',
                'entity_id'      => $companyGrand,
                'node_origin'    => 'MANUAL',
                'sort_order'     => 3,
                'is_active'      => true,
                'row_version'    => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);

        $ids = $this->svc->expandReference(
            $this->tenantId,
            'COMPANY',
            $companyRoot,
            'LEGAL',
            true
        );

        $this->assertContains($companyRoot, $ids);
        $this->assertContains($companyChild, $ids);
        $this->assertContains($companyGrand, $ids);
        $this->assertCount(3, $ids);
    }

    #[Test]
    public function expand_scopes_groups_by_type(): void
    {
        $br1 = (string) Str::uuid();
        $br2 = (string) Str::uuid();

        $result = $this->svc->expandScopes($this->tenantId, [
            [
                'scope_type'        => 'BRANCH',
                'reference_id'      => $br1,
                'hierarchy_purpose' => null,
                'include_subtree'   => false,
            ],
            [
                'scope_type'        => 'BRANCH',
                'reference_id'      => $br2,
                'hierarchy_purpose' => null,
                'include_subtree'   => false,
            ],
        ]);

        $this->assertArrayHasKey('BRANCH', $result);
        $this->assertContains($br1, $result['BRANCH']);
        $this->assertContains($br2, $result['BRANCH']);
    }
}
