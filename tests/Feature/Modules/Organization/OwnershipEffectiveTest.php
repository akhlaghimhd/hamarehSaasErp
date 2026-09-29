<?php

namespace Tests\Feature\Modules\Organization;

use App\Modules\Organization\Services\OwnershipEffectiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OwnershipEffectiveTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private string $parentId;

    private string $childId;

    private string $grandId;

    private OwnershipEffectiveService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        $this->parentId = (string) Str::uuid();
        $this->childId = (string) Str::uuid();
        $this->grandId = (string) Str::uuid();

        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'OWN1',
            'tenant_name' => 'Ownership Tenant',
            'slug'        => 'own-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        foreach ([
            [$this->parentId, 'PAR'],
            [$this->childId, 'CHD'],
            [$this->grandId, 'GRD'],
        ] as [$id, $code]) {
            DB::table('erp_companies')->insert([
                'company_id'  => $id,
                'tenant_id'   => $this->tenantId,
                'code'        => $code,
                'name'        => $code,
                'legal_name'  => $code.' Legal',
                'is_active'   => true,
                'status'      => 'ACTIVE',
                'entity_kind' => 'OPERATING',
                'row_version' => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // Parent owns 80% of Child; remaining 20% minority
        DB::table('erp_company_ownerships')->insert([
            'ownership_id'      => (string) Str::uuid(),
            'tenant_id'         => $this->tenantId,
            'company_id'        => $this->childId,
            'owner_company_id'  => $this->parentId,
            'ownership_percent' => 80,
            'relation_type'     => 'EQUITY',
            'status'            => 1,
            'row_version'       => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Child owns 50% of Grand → Parent effective = 80% * 50% = 40%
        DB::table('erp_company_ownerships')->insert([
            'ownership_id'      => (string) Str::uuid(),
            'tenant_id'         => $this->tenantId,
            'company_id'        => $this->grandId,
            'owner_company_id'  => $this->childId,
            'ownership_percent' => 50,
            'relation_type'     => 'EQUITY',
            'status'            => 1,
            'row_version'       => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);

        $this->svc = app(OwnershipEffectiveService::class);
    }

    #[Test]
    public function direct_owners_and_minority(): void
    {
        $owners = $this->svc->directOwners($this->tenantId, $this->childId);
        $this->assertCount(1, $owners);
        $this->assertEquals(80.0, $owners[0]['ownership_percent']);

        $this->assertEquals(80.0, $this->svc->directOwnershipTotal($this->tenantId, $this->childId));
        $this->assertEquals(20.0, $this->svc->minorityInterestPercent($this->tenantId, $this->childId));
    }

    #[Test]
    public function effective_ownership_multiplies_along_path(): void
    {
        $pct = $this->svc->effectiveOwnershipPercent(
            $this->tenantId,
            $this->parentId,
            $this->grandId
        );

        $this->assertEquals(40.0, $pct);
    }

    #[Test]
    public function snapshot_for_consol_prep(): void
    {
        $snap = $this->svc->ownershipSnapshot($this->tenantId, [
            $this->childId,
            $this->grandId,
        ]);

        $this->assertEquals(20.0, $snap[$this->childId]['minority_interest']);
        $this->assertEquals(50.0, $snap[$this->grandId]['minority_interest']);
    }

    #[Test]
    public function over_100_percent_rejected(): void
    {
        DB::table('erp_company_ownerships')->insert([
            'ownership_id'      => (string) Str::uuid(),
            'tenant_id'         => $this->tenantId,
            'company_id'        => $this->childId,
            'owner_company_id'  => $this->grandId,
            'ownership_percent' => 30,
            'relation_type'     => 'EQUITY',
            'status'            => 1,
            'row_version'       => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // 80 + 30 = 110
        $this->expectException(HttpException::class);
        $this->svc->assertOwnershipPercentSane($this->tenantId, $this->childId);
    }
}
