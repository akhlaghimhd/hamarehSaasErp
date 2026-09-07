<?php

namespace Tests\Feature\Modules\Workflow;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\ProcurementSales\DTOs\CreateSalesOrderDTO;
use App\Modules\ProcurementSales\DTOs\SalesOrderItemDTO;
use App\Modules\ProcurementSales\Services\SalesOrderService;
use App\Modules\Workflow\Services\WorkflowEngineService;
use App\Modules\Workflow\Events\WorkflowTaskCompletedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-WF-04 — SO submitForApproval → complete task → auto confirm via listener
 */
class WorkflowSalesOrderApprovalIntegrationTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $roleId;
    protected string $currencyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'WF_SO_A', 'status' => 1]);
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

    #[Test]
    public function approve_workflow_confirms_sales_order(): void
    {
        $engine = app(WorkflowEngineService::class);
        $soService = app(SalesOrderService::class);

        $engine->upsertDefinition(
            code: 'SALES_ORDER_APPROVAL_V1',
            name: 'SO Approval',
            targetAggregateType: 'sales_orders',
            flowGraph: [
                'initial_state' => 'pending_approval',
                'states' => [
                    'pending_approval' => [
                        'task_name'      => 'Approve SO',
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

        $order = $soService->createSalesOrder(new CreateSalesOrderDTO(
            customerId: (string) Str::uuid(),
            currencyId: $this->currencyId,
            orderDate: '2026-09-07',
            deliveryDate: null,
            warehouseId: (string) Str::uuid(),
            items: [
                new SalesOrderItemDTO(
                    itemId: (string) Str::uuid(),
                    quantity: 5.0,
                    unitPrice: 100.0,
                ),
            ],
        ));

        $pending = $soService->submitForApproval($order->sales_order_id);
        $this->assertSame(SalesOrderService::STATUS_PENDING_APPROVAL, (int) $pending->status);

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
            'target_aggregate_type' => 'sales_orders',
            'target_aggregate_id'   => $order->sales_order_id,
            'previous_state'        => 'pending_approval',
            'current_state'         => 'approved',
            'approved'              => true,
            'instance_status'       => WorkflowEngineService::INSTANCE_COMPLETED,
            'actioned_by'           => $this->userA->user_id,
        ];
        event(WorkflowTaskCompletedV1::EVENT_TYPE, [$payload]);

        $order->refresh();
        $this->assertSame(SalesOrderService::STATUS_CONFIRMED, (int) $order->status);
    }

    #[Test]
    public function reject_workflow_returns_sales_order_to_draft(): void
    {
        $engine = app(WorkflowEngineService::class);
        $soService = app(SalesOrderService::class);

        $engine->upsertDefinition(
            code: 'SALES_ORDER_APPROVAL_V1',
            name: 'SO Approval',
            targetAggregateType: 'sales_orders',
            flowGraph: [
                'initial_state' => 'pending_approval',
                'states' => [
                    'pending_approval' => [
                        'task_name'      => 'Approve SO',
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

        $order = $soService->createSalesOrder(new CreateSalesOrderDTO(
            customerId: (string) Str::uuid(),
            currencyId: $this->currencyId,
            orderDate: '2026-09-07',
            deliveryDate: null,
            warehouseId: (string) Str::uuid(),
            items: [
                new SalesOrderItemDTO(
                    itemId: (string) Str::uuid(),
                    quantity: 2.0,
                    unitPrice: 50.0,
                ),
            ],
        ));

        $soService->submitForApproval($order->sales_order_id);
        $task = $engine->listPendingTasks(
            WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
            $this->roleId,
        )->first();

        $instance = $engine->completeTask($task->task_id, false);
        event(WorkflowTaskCompletedV1::EVENT_TYPE, [[
            'event_type'            => WorkflowTaskCompletedV1::EVENT_TYPE,
            'tenant_id'             => $this->tenantA->tenant_id,
            'task_id'               => $task->task_id,
            'process_instance_id'   => $instance->process_instance_id,
            'target_aggregate_type' => 'sales_orders',
            'target_aggregate_id'   => $order->sales_order_id,
            'previous_state'        => 'pending_approval',
            'current_state'         => 'rejected',
            'approved'              => false,
            'instance_status'       => WorkflowEngineService::INSTANCE_TERMINATED,
            'actioned_by'           => $this->userA->user_id,
        ]]);

        $order->refresh();
        $this->assertSame(SalesOrderService::STATUS_DRAFT, (int) $order->status);
    }
}
