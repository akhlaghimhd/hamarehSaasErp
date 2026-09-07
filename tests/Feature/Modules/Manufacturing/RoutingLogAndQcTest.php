<?php

namespace Tests\Feature\Modules\Manufacturing;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Manufacturing\Services\BomService;
use App\Modules\Manufacturing\Services\ProductionOrderService;
use App\Modules\Manufacturing\Services\ProductionRoutingService;
use App\Modules\Manufacturing\Services\ProductionLogService;
use App\Modules\Manufacturing\Services\QualityInspectionService;
use App\Modules\Manufacturing\Services\WorkCenterService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-MFG-03 — Routing start/complete, labor log, QC dispose
 */
class RoutingLogAndQcTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'MFG_RT_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);
        $this->itemId = (string) Str::uuid();

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
    public function routing_labor_log_and_qc_happy_path(): void
    {
        $wc = app(WorkCenterService::class)->create([
            'code' => 'WC-RT-1',
            'name' => 'Cutting',
            'status' => 1,
        ]);

        $bom = app(BomService::class)->create([
            'item_id'      => $this->itemId,
            'version_code' => 'V1',
            'title'        => 'RT BOM',
            'items'        => [
                [
                    'material_item_id' => (string) Str::uuid(),
                    'quantity'         => 1,
                ],
            ],
        ]);
        app(BomService::class)->approve($bom->bom_id);

        $order = app(ProductionOrderService::class)->create([
            'order_number'     => 'PO-RT-01',
            'item_id'          => $this->itemId,
            'bom_id'           => $bom->bom_id,
            'planned_quantity' => 5,
            'start_date'       => '2026-09-10',
            'due_date'         => '2026-09-15',
        ]);
        app(ProductionOrderService::class)->release($order->production_order_id);

        $routing = app(ProductionRoutingService::class);
        $step = $routing->addStep($order->production_order_id, [
            'work_center_id'            => $wc->work_center_id,
            'operation_sequence'        => 10,
            'operation_name'            => 'Cut',
            'standard_setup_time_hours' => 0.5,
            'standard_run_time_hours'   => 1.0,
        ]);
        $this->assertSame(ProductionRoutingService::STATUS_PENDING, (int) $step->status);

        $started = $routing->start($step->routing_id);
        $this->assertSame(ProductionRoutingService::STATUS_ACTIVE, (int) $started->status);

        $order->refresh();
        $this->assertSame(ProductionOrderService::STATUS_IN_PROGRESS, (int) $order->status);

        $log = app(ProductionLogService::class)->log($order->production_order_id, [
            'log_type'    => ProductionLogService::TYPE_LABOR,
            'routing_id'  => $step->routing_id,
            'hours_spent' => 1.25,
        ]);
        $this->assertSame(1.25, (float) $log->hours_spent);

        $completed = $routing->complete($step->routing_id);
        $this->assertSame(ProductionRoutingService::STATUS_COMPLETED, (int) $completed->status);

        $qc = app(QualityInspectionService::class)->create([
            'inspection_type'      => QualityInspectionService::TYPE_PRODUCTION_OUTPUT,
            'item_id'              => $this->itemId,
            'inspection_number'    => 'QC-001',
            'sample_quantity'      => 5,
            'source_document_type' => 'PRODUCTION_ORDER',
            'source_document_id'   => $order->production_order_id,
        ]);
        $this->assertSame(QualityInspectionService::QC_PENDING, (int) $qc->qc_status);

        $disposed = app(QualityInspectionService::class)->dispose(
            $qc->inspection_id,
            QualityInspectionService::QC_APPROVED,
            4.0,
            1.0,
            'One unit scrap'
        );
        $this->assertSame(QualityInspectionService::QC_APPROVED, (int) $disposed->qc_status);
        $this->assertEquals(4.0, (float) $disposed->accepted_quantity);
        $this->assertEquals(1.0, (float) $disposed->rejected_quantity);
    }
}
