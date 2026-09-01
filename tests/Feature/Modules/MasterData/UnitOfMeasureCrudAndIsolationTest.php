<?php

namespace Tests\Feature\Modules\MasterData;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\MasterData\Models\UnitOfMeasure;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L5-MD-P03 — UnitOfMeasure (Tenant-Owned) CRUD + isolation + soft-delete + row_version
 */
class UnitOfMeasureCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'UOM_A',
            'status'      => 1,
        ]);
        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'UOM_B',
            'status'      => 1,
        ]);

        $this->userA = User::factory()->create(['status' => 1]);
        $this->userB = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'user_id'   => $this->userB->user_id,
            'status'    => 1,
        ]);

        $roleA = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'uom-full',
            'name'      => 'UoM Full',
            'status'    => 1,
        ]);
        $roleB = TenantRole::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'code'      => 'uom-full',
            'name'      => 'UoM Full',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'master-data.unit-of-measure.view',
            'master-data.unit-of-measure.create',
            'master-data.unit-of-measure.update',
            'master-data.unit-of-measure.delete',
        ];

        foreach ([$this->tenantA, $this->tenantB] as $tenant) {
            $role = $tenant->tenant_id === $this->tenantA->tenant_id ? $roleA : $roleB;
            $user = $tenant->tenant_id === $this->tenantA->tenant_id ? $this->userA : $this->userB;

            foreach ($permissionCodes as $code) {
                $perm = TenantPermission::create([
                    'tenant_permission_id' => (string) Str::uuid(),
                    'tenant_id'            => $tenant->tenant_id,
                    'code'                 => $code,
                    'name'                 => $code,
                    'module_name'          => 'MasterData',
                    'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
                    'status'               => 1,
                ]);
                TenantRolePermission::create([
                    'tenant_role_permission_id' => (string) Str::uuid(),
                    'tenant_id'                 => $tenant->tenant_id,
                    'tenant_role_id'            => $role->tenant_role_id,
                    'tenant_permission_id'      => $perm->tenant_permission_id,
                ]);
            }

            TenantUserRole::create([
                'tenant_user_role_id' => (string) Str::uuid(),
                'tenant_id'           => $tenant->tenant_id,
                'user_id'             => $user->user_id,
                'tenant_role_id'      => $role->tenant_role_id,
            ]);
        }

        $this->tokenA = $this->userA->createToken(
            'uom-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;
        $this->tokenB = $this->userB->createToken(
            'uom-b',
            ['tenant:' . $this->tenantB->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function authHeaders(string $token, string $tenantId): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $tenantId,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function can_create_list_show_update_and_soft_delete_unit_of_measure(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/master-data/units-of-measure', [
                'code'              => 'KG',
                'name'              => 'Kilogram',
                'decimal_places'    => 3,
                'conversion_factor' => 1.0,
                'status'            => 1,
            ]);

        $createResponse->assertStatus(201);
        $uomId = $createResponse->json('data.uom_id');
        $this->assertNotEmpty($uomId);

        $this->assertDatabaseHas('units_of_measure', [
            'uom_id'      => $uomId,
            'tenant_id'   => $this->tenantA->tenant_id,
            'code'        => 'KG',
            'row_version' => 1,
        ]);

        $listResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/master-data/units-of-measure');
        $listResponse->assertStatus(200);

        $showResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/master-data/units-of-measure/' . $uomId);
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.code', 'KG');

        $updateResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/master-data/units-of-measure/' . $uomId, [
                'name' => 'Kilogram (updated)',
            ]);
        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('units_of_measure', [
            'uom_id'      => $uomId,
            'name'        => 'Kilogram (updated)',
            'row_version' => 2,
        ]);

        $deleteResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/master-data/units-of-measure/' . $uomId);
        $deleteResponse->assertStatus(200);

        $this->assertSoftDeleted('units_of_measure', ['uom_id' => $uomId]);
    }

    #[Test]
    public function tenant_isolation_prevents_cross_tenant_access(): void
    {
        // Create UoM for tenant A via API
        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/master-data/units-of-measure', [
                'code'              => 'BOX',
                'name'              => 'Box',
                'decimal_places'    => 0,
                'conversion_factor' => 1.0,
                'status'            => 1,
            ]);
        $createResponse->assertStatus(201);
        $uomIdA = $createResponse->json('data.uom_id');

        // Tenant B must not be able to show Tenant A record (403 permission or 404 not found)
        $showAsB = $this->withHeaders($this->authHeaders($this->tokenB, $this->tenantB->tenant_id))
            ->getJson('/api/master-data/units-of-measure/' . $uomIdA);
        $this->assertTrue(
            in_array($showAsB->status(), [403, 404]),
            'Expected isolation deny (403/404), got ' . $showAsB->status()
        );

        // Seed a UoM belonging only to tenant B (bypass TenantScoped for setup)
        $uomB = UnitOfMeasure::withoutGlobalScopes()->create([
            'uom_id'            => (string) Str::uuid(),
            'tenant_id'         => $this->tenantB->tenant_id,
            'code'              => 'PCS',
            'name'              => 'Pieces',
            'decimal_places'    => 0,
            'conversion_factor' => 1.0,
            'status'            => 1,
            'row_version'       => 1,
        ]);

        // Tenant A list must not contain tenant B data
        $listAsA = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/master-data/units-of-measure');
        $listAsA->assertStatus(200);
        $ids = collect($listAsA->json('data'))->pluck('uom_id')->all();
        $this->assertNotContains($uomB->uom_id, $ids);
        $this->assertContains($uomIdA, $ids);

        // Tenant A show of tenant B record must fail
        $showAsA = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/master-data/units-of-measure/' . $uomB->uom_id);
        $this->assertTrue(
            in_array($showAsA->status(), [403, 404]),
            'Expected isolation deny for A viewing B record, got ' . $showAsA->status()
        );
    }

    #[Test]
    public function unauthorized_user_cannot_create_unit_of_measure(): void
    {
        $unauthUser = User::factory()->create(['status' => 1]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $unauthUser->user_id,
            'status'    => 1,
        ]);
        $token = $unauthUser->createToken(
            'uom-unauth',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $response = $this->withHeaders($this->authHeaders($token, $this->tenantA->tenant_id))
            ->postJson('/api/master-data/units-of-measure', [
                'code' => 'X',
                'name' => 'X',
            ]);

        $this->assertTrue(
            in_array($response->status(), [401, 403]),
            'Expected permission deny, got ' . $response->status()
        );
    }
}
