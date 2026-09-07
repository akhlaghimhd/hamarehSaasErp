<?php

namespace Tests\Feature\Modules\Workflow;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseOrderDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseOrderItemDTO;
use App\Modules\ProcurementSales\Services\PurchaseOrderService;
use App\Modules\Workflow\Services\WorkflowEngineService;
use App\Modules\Workflow\Events\WorkflowTaskCompletedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-WF-04 extension — PO submitForApproval → complete task → SENT (approve) or Draft (reject).
 */
class WorkflowPurchaseOrderApprovalIntegrationTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $roleId;
    protected string $currencyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'WF_PO_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);
        $this->roleId = (string) Str::uuid();
        $this->currencyId = (string) Str::uuid();
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

    private function seedDefinition(WorkflowEngineService $engine): void
    {
        $engine->upsertDefinition(
            code: 'PURCHASE_ORDER_APPROVAL_V1',
            name: 'PO Approval',
            targetAggregateType: 'purchase_orders',
            flowGraph: [
                'initial_state' => 'pending_approval',
                'states' => [
                    'pending_approval' => [
                        'task_name'      => 'Approve PO',
                        'assigned_type'  => WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
                        'assigned_to_id' => $this->roleId,
                        'on_approve'     => 'approved',
                        'on_reject'      => 'rejected',
                    ],
                    'approved' => ['terminal' => true],
                    'rejected' => ['terminal' => true],
                ],
            ],
        );
    }

    private function createDraftPo(PurchaseOrderService $poService)
    {
        return $poService->createPurchaseOrder(new CreatePurchaseOrderDTO(
            supplierId: (string) Str::uuid(),
            currencyId: $this->currencyId,
            orderDate: '2026-09-07',
            deliveryDate: null,
            items: [
                new PurchaseOrderItemDTO(
                    itemId: (string) Str::uuid(),
                    quantity: 10.0,
                    unitPrice: 50.0,
                ),
            ],
        ));
    }

    #[Test]
    public function approve_workflow_sends_purchase_order(): void
    {
        $engine = app(WorkflowEngineService::class);
        $poService = app(PurchaseOrderService::class);

        $this->seedDefinition($engine);

        $order = $this->createDraftPo($poService);

        $pending = $poService->submitForApproval($order->purchase_order_id);
        $this->assertSame(PurchaseOrderService::STATUS_PENDING_APPROVAL, (int) $pending->status);

        $tasks = $engine->listPendingTasks(
            WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
            $this->roleId,
        );
        $this->assertCount(1, $tasks);

        $instance = $engine->completeTask($tasks->first()->task_id, true);
        $this->assertSame(WorkflowEngineService::INSTANCE_COMPLETED, (int) $instance->status);

        $payload = [
            'event_type'            => WorkflowTaskCompletedV1::EVENT_TYPE,
            'tenant_id'             => $this->tenantA->tenant_id,
            'task_id'               => $tasks->first()->task_id,
            'process_instance_id'   => $instance->process_instance_id,
            'target_aggregate_type' => 'purchase_orders',
            'target_aggregate_id'   => $order->purchase_order_id,
            'previous_state'        => 'pending_approval',
            'current_state'         => 'approved',
            'approved'              => true,
            'instance_status'       => WorkflowEngineService::INSTANCE_COMPLETED,
            'actioned_by'           => $this->userA->user_id,
        ];
        event(WorkflowTaskCompletedV1::EVENT_TYPE, [$payload]);

        $order->refresh();
        $this->assertSame(PurchaseOrderService::STATUS_SENT, (int) $order->status);
    }

    #[Test]
    public function reject_workflow_returns_purchase_order_to_draft(): void
    {
        $engine = app(WorkflowEngineService::class);
        $poService = app(PurchaseOrderService::class);

        $this->seedDefinition($engine);

        $order = $this->createDraftPo($poService);

        $pending = $poService->submitForApproval($order->purchase_order_id);
        $this->assertSame(PurchaseOrderService::STATUS_PENDING_APPROVAL, (int) $pending->status);

        $tasks = $engine->listPendingTasks(
            WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
            $this->roleId,
        );
        $this->assertCount(1, $tasks);

        $instance = $engine->completeTask($tasks->first()->task_id, false);
        $this->assertSame(WorkflowEngineService::INSTANCE_TERMINATED, (int) $instance->status);

        $payload = [
            'event_type'            => WorkflowTaskCompletedV1::EVENT_TYPE,
            'tenant_id'             => $this->tenantA->tenant_id,
            'task_id'               => $tasks->first()->task_id,
            'process_instance_id'   => $instance->process_instance_id,
            'target_aggregate_type' => 'purchase_orders',
            'target_aggregate_id'   => $order->purchase_order_id,
            'previous_state'        => 'pending_approval',
            'current_state'         => 'rejected',
            'approved'              => false,
            'instance_status'       => WorkflowEngineService::INSTANCE_TERMINATED,
            'actioned_by'           => $this->userA->user_id,
        ];
        event(WorkflowTaskCompletedV1::EVENT_TYPE, [$payload]);

        $order->refresh();
        $this->assertSame(PurchaseOrderService::STATUS_DRAFT, (int) $order->status);
    }
}
