<?php

namespace Tests\Feature\Modules\ProjectManagement;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ProjectTask;
use App\Modules\ProjectManagement\Services\ProjectService;
use App\Modules\ProjectManagement\Services\ProjectTaskService;
use App\Modules\ProjectManagement\Services\ProjectMemberService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-PM-01 — Project activate/complete, task lifecycle, member add/remove
 */
class ProjectTaskMemberLifecycleTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'PM_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);

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
    public function project_task_and_member_lifecycle(): void
    {
        $projectService = app(ProjectService::class);
        $taskService = app(ProjectTaskService::class);
        $memberService = app(ProjectMemberService::class);

        $project = $projectService->create([
            'project_code' => 'PRJ-001',
            'name'         => 'ERP Rollout',
            'start_date'   => '2026-09-01',
            'end_date'     => '2026-12-31',
            'budget'       => 500000000,
        ]);
        $this->assertSame(Project::STATUS_PLANNING, (int) $project->status);

        $active = $projectService->activate($project->project_id);
        $this->assertSame(Project::STATUS_ACTIVE, (int) $active->status);

        $task = $taskService->create($project->project_id, [
            'task_code'       => 'T-10',
            'title'           => 'Discovery',
            'estimated_hours' => 40,
            'priority'        => ProjectTask::PRIORITY_HIGH,
        ]);
        $this->assertSame(ProjectTask::STATUS_TODO, (int) $task->status);

        $started = $taskService->start($task->task_id);
        $this->assertSame(ProjectTask::STATUS_IN_PROGRESS, (int) $started->status);

        $done = $taskService->complete($task->task_id, 36.5);
        $this->assertSame(ProjectTask::STATUS_DONE, (int) $done->status);
        $this->assertEquals(36.5, (float) $done->actual_hours);

        $employeeId = (string) Str::uuid();
        $member = $memberService->add($project->project_id, [
            'employee_id'  => $employeeId,
            'project_role' => 'PROJECT_MANAGER',
        ]);
        $this->assertTrue($member->is_active);

        $removed = $memberService->remove($member->project_member_id);
        $this->assertFalse($removed->is_active);
        $this->assertNotNull($removed->left_at);

        $completed = $projectService->complete($project->project_id);
        $this->assertSame(Project::STATUS_COMPLETED, (int) $completed->status);
        $this->assertNotNull($completed->actual_end_date);
    }
}
