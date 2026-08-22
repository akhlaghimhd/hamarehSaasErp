<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $globalUserA;
    protected User $globalUserB;

    protected function setUp(): void
    {
        parent::setUp();

        // اول کاربرها رو بسازید
        $this->globalUserA = User::factory()->create();
        $this->globalUserB = User::factory()->create();

        // سپس تننت‌ها رو بسازید
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'TENANT_A']);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'TENANT_B']);

        // ارتباط کاربرها با تننت‌ها
        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id' => $this->globalUserA->user_id,
            'status' => 1
        ]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'user_id' => $this->globalUserB->user_id,
            'status' => 1
        ]);

        // نقش‌ها رو بسازید
        TenantRole::factory()->create(['tenant_id' => $this->tenantA->tenant_id, 'code' => 'ROLE_A']);
        TenantRole::factory()->create(['tenant_id' => $this->tenantB->tenant_id, 'code' => 'ROLE_B']);
    }

    /** @test */
    public function database_queries_are_automatically_isolated_by_tenant_scope()
    {
        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);

        $roles = TenantRole::all();

        $this->assertCount(1, $roles);
        $this->assertEquals('ROLE_A', $roles->first()->code);
    }

    /** @test */
    public function user_cannot_access_other_tenants_record_by_direct_id()
    {
        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);

        $roleB = TenantRole::withoutGlobalScopes()->where('code', 'ROLE_B')->first();

        $this->assertNull(TenantRole::find($roleB->tenant_role_id));
    }

    /** @test */
    public function api_endpoints_block_cross_tenant_access_and_prevent_data_leakage()
    {
        // Create a token for user A in tenant A
        $token = $this->globalUserA->createToken('test-token-' . $this->tenantA->tenant_id, ['tenant:' . $this->tenantA->tenant_id])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $this->tenantA->tenant_id
        ])->getJson('/api/identity-core/identity/roles');

        $response->assertStatus(200);
        $responseData = $response->json('data');

        $this->assertCount(1, $responseData);
        $this->assertEquals('ROLE_A', $responseData[0]['code']);
    }
}