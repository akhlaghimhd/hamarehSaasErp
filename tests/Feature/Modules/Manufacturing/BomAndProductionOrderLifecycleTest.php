<?php

namespace Tests\Feature\Modules\Manufacturing;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\BomItem;
use App\Modules\Manufacturing\Services\BomService;
use App\Modules\Manufacturing\Services\ProductionOrderService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-MFG-01 — BOM create/approve + Production Order draft→released
 */
class BomAndProductionOrderLifecycleTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $finishedItemId;
    protected string $materialItemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'MFG_BOM_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);
        $this->finishedItemId = (string) Str::uuid();
        $this->materialItemId = (string) Str::uuid();

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        Context::add('tenant_id', $this->tenantA->tenant_id);
        Context::add('user_id', $this->userA->user_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    #[Test]
    public function bom_create_approve_and_release_production_order(): void
    {
        $bomService = app(BomService::class);
        $poService = app(ProductionOrderService::class);

        $bom = $bomService->create([
            'item_id'      => $this->finishedItemId,
            'version_code' => 'V1',
            'title'        => 'Finished Product BOM',
            'items'        => [
                [
                    'material_item_id' => $this->materialItemId,
                    'quantity'         => 2.5,
                    'scrap_percentage' => 1.5,
                ],
            ],
        ]);

        $this->assertSame(BomService::STATUS_DRAFT, (int) $bom->status);
        $this->assertCount(1, $bom->items);

        $approved = $bomService->approve($bom->bom_id);
        $this->assertSame(BomService::STATUS_APPROVED, (int) $approved->status);

        $order = $poService->create([
            'order_number'     => 'PO-MFG-001',
            'item_id'          => $this->finishedItemId,
            'bom_id'           => $approved->bom_id,
            'planned_quantity' => 100,
            'start_date'       => '2026-09-10',
            'due_date'         => '2026-09-20',
        ]);

        $this->assertSame(ProductionOrderService::STATUS_DRAFT, (int) $order->status);

        $released = $poService->release($order->production_order_id);
        $this->assertSame(ProductionOrderService::STATUS_RELEASED, (int) $released->status);

        $outbox = DB::table('event_outbox')
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->where('event_type', 'manufacturing.production_order.released.v1')
            ->where('aggregate_id', $order->production_order_id)
            ->first();

        $this->assertNotNull($outbox);
    }

    #[Test]
    public function cannot_release_production_order_with_draft_bom(): void
    {
        $bomService = app(BomService::class);
        $poService = app(ProductionOrderService::class);

        $bom = $bomService->create([
            'item_id'      => $this->finishedItemId,
            'version_code' => 'V2',
            'title'        => 'Draft only BOM',
            'items'        => [
                [
                    'material_item_id' => $this->materialItemId,
                    'quantity'         => 1,
                ],
            ],
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);

        $poService->create([
            'order_number'     => 'PO-MFG-002',
            'item_id'          => $this->finishedItemId,
            'bom_id'           => $bom->bom_id,
            'planned_quantity' => 10,
            'start_date'       => '2026-09-10',
            'due_date'         => '2026-09-15',
        ]);
    }

    #[Test]
    public function bom_isolation_across_tenants(): void
    {
        $bomId = (string) Str::uuid();
        Bom::withoutGlobalScopes()->create([
            'bom_id'       => $bomId,
            'tenant_id'    => $this->tenantA->tenant_id,
            'item_id'      => $this->finishedItemId,
            'version_code' => 'ISO',
            'title'        => 'Tenant A BOM',
            'is_active'    => true,
            'status'       => BomService::STATUS_DRAFT,
            'row_version'  => 1,
        ]);

        $tenantB = Tenant::factory()->create(['tenant_code' => 'MFG_BOM_B', 'status' => 1]);

        $cross = Bom::withoutGlobalScopes()
            ->where('tenant_id', $tenantB->tenant_id)
            ->where('bom_id', $bomId)
            ->first();

        $this->assertNull($cross);
    }
}
