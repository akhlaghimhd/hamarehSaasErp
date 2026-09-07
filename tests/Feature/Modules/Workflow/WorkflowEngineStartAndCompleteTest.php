<?php

namespace Tests\Feature\Modules\Workflow;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Workflow\Models\ProcessDefinition;
use App\Modules\Workflow\Models\ProcessInstance;
use App\Modules\Workflow\Models\Task;
use App\Modules\Workflow\Services\WorkflowEngineService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-WF-00/01 — Start process instance and approve first task
 */
class WorkflowEngineStartAndCompleteTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $roleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'WF_A',
            'status'      => 1,
        ]);
        $this->userA = User::factory()->create(['status' => 1]);
        $this->roleId = (string) Str::uuid();

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

    protected function sampleGraph(): array
    {
        return [
            'initial_state' => 'pending_approval',
            'states'        => [
                'pending_approval' => [
                    'task_name'      => 'Approve sales order',
                    'assigned_type'  => WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
                    'assigned_to_id' => $this->roleId,
                    'on_approve'     => 'approved',
                    'on_reject'      => 'rejected',
                ],
                'approved' => ['terminal' => true],
                'rejected' => ['terminal' => true],
            ],
        ];
    }

    #[Test]
    public function can_upsert_definition_start_instance_and_approve_task(): void
    {
        $engine = app(WorkflowEngineService::class);

        $definition = $engine->upsertDefinition(
            code: 'SALES_ORDER_APPROVAL_V1',
            name: 'Sales Order Approval',
            targetAggregateType: 'sales_orders',
            flowGraph: $this->sampleGraph(),
        );

        $this->assertNotEmpty($definition->process_definition_id);
        $this->assertSame('SALES_ORDER_APPROVAL_V1', $definition->code);
        $this->assertTrue($definition->is_active);

        $aggregateId = (string) Str::uuid();
        $instance = $engine->startInstance(
            definitionCode: 'SALES_ORDER_APPROVAL_V1',
            targetAggregateType: 'sales_orders',
            targetAggregateId: $aggregateId,
            contextSnapshot: [
                'order_number' => 'SO-E2E-1',
                'total_amount' => '1500.0000',
            ],
        );

        $this->assertSame(WorkflowEngineService::INSTANCE_RUNNING, (int) $instance->status);
        $this->assertSame('pending_approval', $instance->current_state);
        $this->assertSame($aggregateId, $instance->target_aggregate_id);
        $this->assertCount(1, $instance->tasks);

        $task = $instance->tasks->first();
        $this->assertSame(WorkflowEngineService::TASK_PENDING, (int) $task->status);
        $this->assertSame($this->roleId, $task->assigned_to_id);
        $this->assertSame('Approve sales order', $task->task_name);
        $this->assertSame('SO-E2E-1', $task->context_snapshots['order_number']);

        $completed = $engine->completeTask($task->task_id, true);

        $this->assertSame('approved', $completed->current_state);
        $this->assertSame(WorkflowEngineService::INSTANCE_COMPLETED, (int) $completed->status);

        $task->refresh();
        $this->assertSame(WorkflowEngineService::TASK_APPROVED, (int) $task->status);
        $this->assertNotNull($task->actioned_at);
        $this->assertSame($this->userA->user_id, $task->actioned_by);
    }

    #[Test]
    public function reject_terminates_instance(): void
    {
        $engine = app(WorkflowEngineService::class);

        $engine->upsertDefinition(
            code: 'PO_APPROVAL_V1',
            name: 'PO Approval',
            targetAggregateType: 'purchase_orders',
            flowGraph: $this->sampleGraph(),
        );

        $instance = $engine->startInstance(
            definitionCode: 'PO_APPROVAL_V1',
            targetAggregateType: 'purchase_orders',
            targetAggregateId: (string) Str::uuid(),
        );

        $task = $instance->tasks->first();
        $completed = $engine->completeTask($task->task_id, false);

        $this->assertSame('rejected', $completed->current_state);
        $this->assertSame(WorkflowEngineService::INSTANCE_TERMINATED, (int) $completed->status);
        $this->assertSame(WorkflowEngineService::TASK_REJECTED, (int) $task->fresh()->status);
    }

    #[Test]
    public function tenant_isolation_on_definitions(): void
    {
        $engine = app(WorkflowEngineService::class);
        $engine->upsertDefinition(
            code: 'ISO_FLOW',
            name: 'Iso',
            targetAggregateType: 'sales_orders',
            flowGraph: $this->sampleGraph(),
        );

        $tenantB = Tenant::factory()->create(['tenant_code' => 'WF_B', 'status' => 1]);
        Context::add('tenant_id', $tenantB->tenant_id);
        TenantContext::getInstance()->setTenantId($tenantB->tenant_id);
        app()->instance('current_tenant_id', $tenantB->tenant_id);

        $count = ProcessDefinition::query()->where('code', 'ISO_FLOW')->count();
        $this->assertSame(0, $count);
    }
}
