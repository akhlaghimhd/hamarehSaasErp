<?php

namespace Tests\Feature\Modules\SaasPlatform;

use App\Modules\SaasPlatform\Models\Plan;
use App\Modules\SaasPlatform\Models\PlanVersion;
use App\Modules\SaasPlatform\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $planService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planService = app(PlanService::class);
    }

    public function test_create_plan_creates_plan_and_first_version(): void
    {
        $plan = $this->planService->createPlan('BASIC', 'Basic Plan');

        $this->assertDatabaseHas('plans', [
            'plan_id' => $plan->plan_id,
            'code'    => 'BASIC',
            'name'    => 'Basic Plan',
            'status'  => 1,
        ]);

        $this->assertDatabaseHas('plan_versions', [
            'plan_id'        => $plan->plan_id,
            'version_number' => 1,
            'status'         => 1,
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'aggregate_type' => 'plans',
            'event_type'     => 'SaasAdmin.PlanCreated.v1',
            'status'         => 1,
        ]);

        $this->assertCount(1, $plan->versions);
    }

    public function test_create_plan_throws_exception_on_duplicate_code(): void
    {
        $this->planService->createPlan('BASIC', 'Basic Plan');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan code [BASIC] already exists.');

        $this->planService->createPlan('BASIC', 'Another Basic Plan');
    }

    public function test_create_plan_version_successfully(): void
    {
        $plan = $this->planService->createPlan('PRO', 'Pro Plan');

        $version = $this->planService->createPlanVersion($plan->plan_id, 2);

        $this->assertDatabaseHas('plan_versions', [
            'plan_version_id' => $version->plan_version_id,
            'plan_id'         => $plan->plan_id,
            'version_number'  => 2,
            'status'          => 1,
        ]);
    }

    public function test_create_plan_version_throws_on_duplicate_version_number(): void
    {
        $plan = $this->planService->createPlan('ENTERPRISE', 'Enterprise Plan');

        $this->expectException(\InvalidArgumentException::class);

        $this->planService->createPlanVersion($plan->plan_id, 1);
    }

    public function test_list_active_plans_returns_only_active(): void
    {
        $this->planService->createPlan('ACTIVE1', 'Active Plan 1');
        $this->planService->createPlan('ACTIVE2', 'Active Plan 2');

        $plans = $this->planService->listActivePlans();

        $this->assertCount(2, $plans);
        $this->assertTrue($plans->every(fn ($p) => $p->status === 1));
    }

    public function test_soft_delete_plan(): void
    {
        $plan = $this->planService->createPlan('TO_DELETE', 'Plan to Delete');

        $result = $this->planService->softDeletePlan($plan->plan_id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('plans', ['plan_id' => $plan->plan_id]);
    }
}