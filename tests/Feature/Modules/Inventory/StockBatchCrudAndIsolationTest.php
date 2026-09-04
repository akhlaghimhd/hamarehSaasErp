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
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockBatch;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-09 — StockBatch CRUD + Tenant Isolation + SoftDelete + QC
 */
class StockBatchCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $tokenA;
    protected string $itemAId;
    protected string $itemBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_BATCH_A',
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
            'code'      => 'inv-batch-mgr',
            'name'      => 'Batch Manager',
            'status'    => 1,
        ]);

        foreach ([
            'inventory.stock-batch.view',
            'inventory.stock-batch.create',
            'inventory.stock-batch.update',
            'inventory.stock-batch.delete',
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
            'batch-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'INV_BATCH_B',
            'status'      => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $itemA = Item::withoutGlobalScopes()->create([
            'item_id'           => (string) Str::uuid(),
            'tenant_id'         => $this->tenantA->tenant_id,
            'item_group_id'     => (string) Str::uuid(),
            'uom_id'            => (string) Str::uuid(),
            'code'              => 'ITEM-BATCH-A',
            'name'              => 'Item Batch A',
            'item_type'         => 1,
            'valuation_method'  => 1,
            'status'            => 1,
        ]);
        $this->itemAId = $itemA->item_id;

        $itemB = Item::withoutGlobalScopes()->create([
            'item_id'           => (string) Str::uuid(),
            'tenant_id'         => $this->tenantB->tenant_id,
            'item_group_id'     => (string) Str::uuid(),
            'uom_id'            => (string) Str::uuid(),
            'code'              => 'ITEM-BATCH-B',
            'name'              => 'Item Batch B',
            'item_type'         => 1,
            'valuation_method'  => 1,
            'status'            => 1,
        ]);
        $this->itemBId = $itemB->item_id;
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
    public function can_create_list_show_update_and_soft_delete_stock_batch(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-batches', [
                'item_id'            => $this->itemAId,
                'batch_number'       => 'LOT-2026-001',
                'quantity_produced'  => '100.0000',
                'quantity_remaining' => '100.0000',
                'production_date'    => '2026-08-01',
                'expiration_date'    => '2027-08-01',
                'qc_status'          => 1,
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.batch_number', 'LOT-2026-001');

        $batchId = $createResponse->json('data.batch_id');
        $this->assertNotEmpty($batchId);

        $this->assertDatabaseHas('inv_stock_batches', [
            'batch_id'     => $batchId,
            'tenant_id'    => $this->tenantA->tenant_id,
            'item_id'      => $this->itemAId,
            'batch_number' => 'LOT-2026-001',
            'qc_status'    => 1,
        ]);

        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-batches?item_id=' . $this->itemAId);

        $indexResponse->assertStatus(200)->assertJsonPath('success', true);
        $ids = collect($indexResponse->json('data'))->pluck('batch_id')->toArray();
        $this->assertContains($batchId, $ids);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-batches/' . $batchId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.batch_id', $batchId);

        $updateResponse = $this->withHeaders($this->authHeaders())
            ->putJson('/api/inventory/stock-batches/' . $batchId, [
                'qc_status'          => 2,
                'quantity_remaining' => '80.0000',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.qc_status', 2);

        $deleteResponse = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/inventory/stock-batches/' . $batchId);

        $deleteResponse->assertStatus(200)->assertJsonPath('success', true);

        $this->assertSoftDeleted('inv_stock_batches', [
            'batch_id'  => $batchId,
            'tenant_id' => $this->tenantA->tenant_id,
        ]);
    }

    #[Test]
    public function tenant_a_cannot_see_batch_of_tenant_b(): void
    {
        $batchB = StockBatch::withoutGlobalScopes()->create([
            'batch_id'           => (string) Str::uuid(),
            'tenant_id'          => $this->tenantB->tenant_id,
            'item_id'            => $this->itemBId,
            'batch_number'       => 'LOT-B-ONLY',
            'quantity_produced'  => 50,
            'quantity_remaining' => 50,
            'qc_status'          => 2,
        ]);

        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-batches');

        $indexResponse->assertStatus(200);
        $ids = collect($indexResponse->json('data'))->pluck('batch_id')->toArray();
        $this->assertNotContains($batchB->batch_id, $ids);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/stock-batches/' . $batchB->batch_id);

        $showResponse->assertStatus(404);
    }

    #[Test]
    public function create_rejects_item_of_other_tenant(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-batches', [
                'item_id'            => $this->itemBId,
                'batch_number'       => 'LOT-X',
                'quantity_produced'  => '10',
                'quantity_remaining' => '10',
                'qc_status'          => 1,
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function remaining_cannot_exceed_produced(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-batches', [
                'item_id'            => $this->itemAId,
                'batch_number'       => 'LOT-OVER',
                'quantity_produced'  => '10',
                'quantity_remaining' => '20',
                'qc_status'          => 1,
            ]);

        $response->assertStatus(422);
    }
}
