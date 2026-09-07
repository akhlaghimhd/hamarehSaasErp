<?php

namespace Tests\Feature\Modules\Workflow;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Workflow\Services\WorkflowEngineService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-WF-02 — Worklist query + multi-step completion path
 */
class WorkflowWorklistAndApiTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $roleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'WF_WL_A',
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
    public function worklist_returns_only_pending_tasks_for_assignee(): void
    {
        $engine = app(WorkflowEngineService::class);
        $otherRole = (string) Str::uuid();

        $engine->upsertDefinition(
            code: 'WL_FLOW',
            name: 'Worklist Flow',
            targetAggregateType: 'sales_orders',
            flowGraph: [
                'initial_state' => 'pending_approval',
                'states' => [
                    'pending_approval' => [
                        'task_name'      => 'Approve',
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

        $engine->startInstance('WL_FLOW', 'sales_orders', (string) Str::uuid(), ['ref' => 'A']);
        $engine->startInstance('WL_FLOW', 'sales_orders', (string) Str::uuid(), ['ref' => 'B']);

        $engine->upsertDefinition(
            code: 'WL_OTHER',
            name: 'Other',
            targetAggregateType: 'purchase_orders',
            flowGraph: [
                'initial_state' => 'pending_approval',
                'states' => [
                    'pending_approval' => [
                        'task_name'      => 'Other approve',
                        'assigned_type'  => WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
                        'assigned_to_id' => $otherRole,
                        'on_approve'     => 'approved',
                        'on_reject'      => 'rejected',
                    ],
                    'approved' => ['terminal' => true],
                    'rejected' => ['terminal' => true],
                ],
            ],
        );
        $engine->startInstance('WL_OTHER', 'purchase_orders', (string) Str::uuid());

        $mine = $engine->listPendingTasks(
            WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
            $this->roleId,
        );

        $this->assertCount(2, $mine);
        foreach ($mine as $task) {
            $this->assertSame($this->roleId, $task->assigned_to_id);
            $this->assertSame(WorkflowEngineService::TASK_PENDING, (int) $task->status);
        }

        $other = $engine->listPendingTasks(
            WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
            $otherRole,
        );
        $this->assertCount(1, $other);

        $engine->completeTask($mine->first()->task_id, true);
        $mineAfter = $engine->listPendingTasks(
            WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
            $this->roleId,
        );
        $this->assertCount(1, $mineAfter);
    }
}
