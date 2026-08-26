<?php

namespace Tests\Feature\Modules\SaasAdmin;

use Tests\TestCase;
use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\SaasAdmin\Models\PlanVersion;
use App\Modules\SaasAdmin\Services\PlanService;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SubscriptionPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $authorizedUser;
    protected User $unauthorizedUser;
    protected string $authorizedToken;
    protected string $unauthorizedToken;
    protected PlanVersion $planVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['status' => 1]);

        $this->authorizedUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->authorizedUser->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'sub-manager',
            'name'      => 'Subscription Manager',
            'status'    => 1,
        ]);

        foreach (['saas-admin.subscription.create', 'saas-admin.subscription.cancel'] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'SaasAdmin',
                'action_type'          => 'CREATE',
                'status'               => 1,
            ]);

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenant->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->authorizedUser->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->authorizedToken = $this->authorizedUser->createToken(
            'sub-auth-token',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->unauthorizedUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->unauthorizedUser->user_id,
            'status'    => 1,
        ]);

        $this->unauthorizedToken = $this->unauthorizedUser->createToken(
            'sub-unauth-token',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);

        $plan = app(PlanService::class)->createPlan('BASIC', 'Basic Plan');
        $this->planVersion = $plan->versions->first();
    }

    public function test_authorized_user_can_create_and_cancel_subscription(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/saas-admin/subscriptions', [
            'tenant_id'       => $this->tenant->tenant_id,
            'plan_version_id' => $this->planVersion->plan_version_id,
            'start_date'      => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $subId = $response->json('data.subscription_id');

        $cancelResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson("/api/saas-admin/subscriptions/{$subId}/cancel");

        $cancelResponse->assertStatus(200);
    }

    public function test_unauthorized_user_cannot_create_or_cancel(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->unauthorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/saas-admin/subscriptions', [
            'tenant_id'       => $this->tenant->tenant_id,
            'plan_version_id' => $this->planVersion->plan_version_id,
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/saas-admin/subscriptions', [
            'tenant_id'       => $this->tenant->tenant_id,
            'plan_version_id' => $this->planVersion->plan_version_id,
        ]);

        $response->assertStatus(401);
    }
}