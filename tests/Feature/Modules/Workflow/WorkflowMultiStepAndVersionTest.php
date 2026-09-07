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
 * L6-WF-05 — Multi-step graph + definition versioning by code (V1 vs V2)
 */
class WorkflowMultiStepAndVersionTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $roleFinance;
    protected string $roleCeo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'WF_MS_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);
        $this->roleFinance = (string) Str::uuid();
        $this->roleCeo = (string) Str::uuid();
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
    public function multi_step_finance_then_ceo_approval(): void
    {
        $engine = app(WorkflowEngineService::class);

        $engine->upsertDefinition(
            code: 'SO_TWO_STEP_V1',
            name: 'SO Two Step',
            targetAggregateType: 'sales_orders',
            flowGraph: [
                'initial_state' => 'finance_review',
                'states' => [
                    'finance_review' => [
                        'task_name'      => 'Finance review',
                        'assigned_type'  => WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
                        'assigned_to_id' => $this->roleFinance,
                        'on_approve'     => 'ceo_review',
                        'on_reject'      => 'rejected',
                    ],
                    'ceo_review' => [
                        'task_name'      => 'CEO review',
                        'assigned_type'  => WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
                        'assigned_to_id' => $this->roleCeo,
                        'on_approve'     => 'approved',
                        'on_reject'      => 'rejected',
                    ],
                    'approved' => ['terminal' => true],
                    'rejected' => ['terminal' => true],
                ],
            ],
        );

        $instance = $engine->startInstance(
            'SO_TWO_STEP_V1',
            'sales_orders',
            (string) Str::uuid(),
        );

        $this->assertSame('finance_review', $instance->current_state);
        $this->assertCount(1, $instance->tasks);

        $financeTask = $instance->tasks->first();
        $this->assertSame($this->roleFinance, $financeTask->assigned_to_id);

        $afterFinance = $engine->completeTask($financeTask->task_id, true);
        $this->assertSame('ceo_review', $afterFinance->current_state);
        $this->assertSame(WorkflowEngineService::INSTANCE_RUNNING, (int) $afterFinance->status);

        $pendingCeo = $engine->listPendingTasks(
            WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
            $this->roleCeo,
        );
        $this->assertCount(1, $pendingCeo);

        $completed = $engine->completeTask($pendingCeo->first()->task_id, true);
        $this->assertSame('approved', $completed->current_state);
        $this->assertSame(WorkflowEngineService::INSTANCE_COMPLETED, (int) $completed->status);
    }

    #[Test]
    public function definition_version_v2_does_not_break_running_v1_instance(): void
    {
        $engine = app(WorkflowEngineService::class);

        $graphV1 = [
            'initial_state' => 'pending_approval',
            'states' => [
                'pending_approval' => [
                    'task_name'      => 'Approve V1',
                    'assigned_type'  => WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
                    'assigned_to_id' => $this->roleFinance,
                    'on_approve'     => 'approved',
                    'on_reject'      => 'rejected',
                ],
                'approved' => ['terminal' => true],
                'rejected' => ['terminal' => true],
            ],
        ];

        $engine->upsertDefinition('SO_VER_V1', 'V1', 'sales_orders', $graphV1);
        $instance = $engine->startInstance('SO_VER_V1', 'sales_orders', (string) Str::uuid());

        $engine->upsertDefinition(
            'SO_VER_V2',
            'V2',
            'sales_orders',
            [
                'initial_state' => 'pending_approval',
                'states' => [
                    'pending_approval' => [
                        'task_name'      => 'Approve V2',
                        'assigned_type'  => WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
                        'assigned_to_id' => $this->roleCeo,
                        'on_approve'     => 'approved',
                        'on_reject'      => 'rejected',
                    ],
                    'approved' => ['terminal' => true],
                    'rejected' => ['terminal' => true],
                ],
            ],
        );

        $task = $instance->tasks->first();
        $done = $engine->completeTask($task->task_id, true);
        $this->assertSame('approved', $done->current_state);
        $this->assertSame(WorkflowEngineService::INSTANCE_COMPLETED, (int) $done->status);
    }
}
