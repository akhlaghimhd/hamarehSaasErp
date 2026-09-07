<?php

namespace Tests\Feature\Modules\ProjectManagement;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\ProjectManagement\Models\ResourceAllocation;
use App\Modules\ProjectManagement\Services\ProjectService;
use App\Modules\ProjectManagement\Services\ProjectTaskService;
use App\Modules\ProjectManagement\Services\ResourceAllocationService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-PM-02 — allocate / list / release resource on task
 */
class ResourceAllocationTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'PM_RES_A', 'status' => 1]);
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
    public function allocate_and_release_resource_on_task(): void
    {
        $project = app(ProjectService::class)->create([
            'project_code' => 'PRJ-RES',
            'name'         => 'Resource Demo',
            'start_date'   => '2026-09-01',
        ]);
        app(ProjectService::class)->activate($project->project_id);

        $task = app(ProjectTaskService::class)->create($project->project_id, [
            'task_code' => 'T-RES',
            'title'     => 'Install',
        ]);

        $svc = app(ResourceAllocationService::class);
        $alloc = $svc->allocate($task->task_id, [
            'resource_type'      => ResourceAllocation::TYPE_HUMAN,
            'resource_id'        => (string) Str::uuid(),
            'allocated_quantity' => 1,
            'start_date'         => '2026-09-10',
            'end_date'           => '2026-09-20',
        ]);

        $this->assertSame(ResourceAllocation::TYPE_HUMAN, (int) $alloc->resource_type);
        $this->assertCount(1, $svc->listForTask($task->task_id));

        $svc->release($alloc->allocation_id);
        $this->assertCount(0, $svc->listForTask($task->task_id));
    }
}
