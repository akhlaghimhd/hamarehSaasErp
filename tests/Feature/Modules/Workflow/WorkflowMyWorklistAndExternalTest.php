<?php

namespace Tests\Feature\Modules\Workflow;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\Workflow\Services\WorkflowEngineService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-WF-06 / L6-WF-07 — my worklist by user roles + external BP assignment
 */
class WorkflowMyWorklistAndExternalTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $roleId;
    protected string $bpId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'WF_MY_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);
        $this->roleId = (string) Str::uuid();
        $this->bpId = (string) Str::uuid();

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        Context::add('tenant_id', $this->tenantA->tenant_id);
        Context::add('user_id', $this->userA->user_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        TenantRole::withoutGlobalScopes()->forceCreate([
            'tenant_role_id' => $this->roleId,
            'tenant_id'      => $this->tenantA->tenant_id,
            'code'           => 'wf-approver',
            'name'           => 'WF Approver',
            'status'         => 1,
        ]);

        TenantUserRole::withoutGlobalScopes()->forceCreate([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenantA->tenant_id,
            'user_id'             => $this->userA->user_id,
            'tenant_role_id'      => $this->roleId,
            'created_by'          => $this->userA->user_id,
        ]);
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    #[Test]
    public function my_worklist_returns_tasks_for_user_roles(): void
    {
        $engine = app(WorkflowEngineService::class);

        $engine->upsertDefinition(
            code: 'MY_WL',
            name: 'My WL',
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

        $engine->startInstance('MY_WL', 'sales_orders', (string) Str::uuid());
        $engine->startInstance('MY_WL', 'sales_orders', (string) Str::uuid());

        $mine = $engine->listPendingTasksForCurrentUser();
        $this->assertCount(2, $mine);
    }

    #[Test]
    public function external_partner_worklist_isolation(): void
    {
        $engine = app(WorkflowEngineService::class);
        $otherBp = (string) Str::uuid();

        $engine->upsertDefinition(
            code: 'EXT_BP_FLOW',
            name: 'External',
            targetAggregateType: 'purchase_orders',
            flowGraph: [
                'initial_state' => 'partner_ack',
                'states' => [
                    'partner_ack' => [
                        'task_name'      => 'Partner acknowledge',
                        'assigned_type'  => WorkflowEngineService::ASSIGN_EXTERNAL_BP,
                        'assigned_to_id' => $this->bpId,
                        'on_approve'     => 'approved',
                        'on_reject'      => 'rejected',
                    ],
                    'approved' => ['terminal' => true],
                    'rejected' => ['terminal' => true],
                ],
            ],
        );

        $engine->startInstance('EXT_BP_FLOW', 'purchase_orders', (string) Str::uuid());

        $mine = $engine->listPendingTasksForExternalPartner($this->bpId);
        $this->assertCount(1, $mine);
        $this->assertSame(WorkflowEngineService::ASSIGN_EXTERNAL_BP, (int) $mine->first()->assigned_type);

        $other = $engine->listPendingTasksForExternalPartner($otherBp);
        $this->assertCount(0, $other);
    }
}
