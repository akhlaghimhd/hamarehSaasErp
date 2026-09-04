<?php

namespace Tests\Feature\Modules\Inventory;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-03 — Location CRUD + Tenant Isolation + SoftDelete
 */
class LocationCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $tokenA;
    protected string $warehouseAId;
    protected string $warehouseBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_LOC_A',
            'status'      => 1,
        ]);

        $this->userA = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'inv-loc-mgr',
            'name'      => 'Location Manager',
            'status'    => 1,
        ]);

        foreach ([
            'inventory.location.view',
            'inventory.location.create',
            'inventory.location.update',
            'inventory.location.delete',
        ] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenantA->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Inventory',
                'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
                'status'               => 1,
            ]);
            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenantA->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenantA->tenant_id,
            'user_id'             => $this->userA->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->tokenA = $this->userA->createToken(
            'loc-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'INV_LOC_B',
            'status'      => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $whA = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-LOC-A',
            'name'         => 'Warehouse A',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseAId = $whA->warehouse_id;

        $whB = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantB->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-LOC-B',
            'name'         => 'Warehouse B',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseBId = $whB->warehouse_id;
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->tokenA,
            'X-Tenant-ID'   => $this->tenantA->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function can_create_list_show_update_and_soft_delete_location(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/locations', [
                'warehouse_id' => $this->warehouseAId,
                'code'         => 'BIN-A1',
                'name'         => 'Bin A1',
                'aisle'        => 'A',
                'rack'         => '1',
                'shelf'        => '1',
                'status'       => 1,
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'BIN-A1');

        $locationId = $createResponse->json('data.location_id');
        $this->assertNotEmpty($locationId);

        $this->assertDatabaseHas('inv_locations', [
            'location_id'  => $locationId,
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $this->warehouseAId,
            'code'         => 'BIN-A1',
        ]);

        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/locations?warehouse_id=' . $this->warehouseAId);

        $indexResponse->assertStatus(200)->assertJsonPath('success', true);
        $ids = collect($indexResponse->json('data'))->pluck('location_id')->toArray();
        $this->assertContains($locationId, $ids);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/locations/' . $locationId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.location_id', $locationId);

        $updateResponse = $this->withHeaders($this->authHeaders())
            ->putJson('/api/inventory/locations/' . $locationId, [
                'name'   => 'Bin A1 Updated',
                'status' => 2,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Bin A1 Updated');

        $deleteResponse = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/inventory/locations/' . $locationId);

        $deleteResponse->assertStatus(200)->assertJsonPath('success', true);

        $this->assertSoftDeleted('inv_locations', [
            'location_id' => $locationId,
            'tenant_id'   => $this->tenantA->tenant_id,
        ]);
    }

    #[Test]
    public function tenant_a_cannot_see_location_of_tenant_b(): void
    {
        $locB = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantB->tenant_id,
            'warehouse_id' => $this->warehouseBId,
            'code'         => 'BIN-B-ONLY',
            'name'         => 'Tenant B Bin',
            'status'       => 1,
        ]);

        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/locations');

        $indexResponse->assertStatus(200);
        $ids = collect($indexResponse->json('data'))->pluck('location_id')->toArray();
        $this->assertNotContains($locB->location_id, $ids);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/locations/' . $locB->location_id);

        $showResponse->assertStatus(404);
    }

    #[Test]
    public function create_rejects_warehouse_of_other_tenant(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/locations', [
                'warehouse_id' => $this->warehouseBId,
                'code'         => 'BIN-X',
                'name'         => 'Invalid WH',
                'status'       => 1,
            ]);

        $response->assertStatus(422);
    }
}
