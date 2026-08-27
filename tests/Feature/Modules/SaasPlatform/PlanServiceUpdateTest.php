<?php

namespace Tests\Feature\Modules\SaasPlatform;

use App\Modules\SaasPlatform\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanServiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $planService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planService = app(PlanService::class);
    }

    public function test_update_plan_name_and_status(): void
    {
        $plan = $this->planService->createPlan('BASIC', 'Basic Plan');

        $updated = $this->planService->updatePlan($plan->plan_id, 'Basic Plan Updated', 2);

        $this->assertEquals('Basic Plan Updated', $updated->name);
        $this->assertEquals(2, $updated->status);
    }

    public function test_soft_delete_plan(): void
    {
        $plan = $this->planService->createPlan('TO_DELETE', 'Delete Me');

        $result = $this->planService->softDeletePlan($plan->plan_id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('plans', ['plan_id' => $plan->plan_id]);
    }
}