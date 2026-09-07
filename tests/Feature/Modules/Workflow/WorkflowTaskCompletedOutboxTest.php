<?php

namespace Tests\Feature\Modules\Workflow;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Workflow\Services\WorkflowEngineService;
use App\Modules\Workflow\Events\WorkflowTaskCompletedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-WF-03 — completeTask publishes workflow.task.completed.v1 to event_outbox
 */
class WorkflowTaskCompletedOutboxTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $roleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'WF_OUT_A',
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

    #[Test]
    public function complete_task_publishes_outbox_event_with_aggregate_refs(): void
    {
        $engine = app(WorkflowEngineService::class);

        $engine->upsertDefinition(
            code: 'OUTBOX_FLOW',
            name: 'Outbox Flow',
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

        $aggregateId = (string) Str::uuid();
        $instance = $engine->startInstance(
            'OUTBOX_FLOW',
            'sales_orders',
            $aggregateId,
            ['order_number' => 'SO-OUT-1'],
        );

        $task = $instance->tasks->first();
        $engine->completeTask($task->task_id, true);

        $outbox = DB::table('event_outbox')
            ->where('event_type', WorkflowTaskCompletedV1::EVENT_TYPE)
            ->where('aggregate_id', $task->task_id)
            ->first();

        $this->assertNotNull($outbox);
        $this->assertSame($this->tenantA->tenant_id, $outbox->tenant_id);
        $this->assertSame('wf_tasks', $outbox->aggregate_type);

        $payload = json_decode($outbox->payload, true);
        $this->assertTrue($payload['approved']);
        $this->assertSame('sales_orders', $payload['target_aggregate_type']);
        $this->assertSame($aggregateId, $payload['target_aggregate_id']);
        $this->assertSame('pending_approval', $payload['previous_state']);
        $this->assertSame('approved', $payload['current_state']);
        $this->assertSame(
            WorkflowEngineService::INSTANCE_COMPLETED,
            (int) $payload['instance_status']
        );
        $this->assertSame($this->userA->user_id, $payload['actioned_by']);
    }
}
