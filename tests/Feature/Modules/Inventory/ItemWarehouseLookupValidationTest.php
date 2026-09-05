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
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Services\ItemLookupService;
use App\Modules\Inventory\Services\WarehouseLookupService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-10 — Logical Item/Warehouse Lookup validation (tenant-scoped, no cross-tenant).
 */
class ItemWarehouseLookupValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $tokenA;
    protected string $itemAId;
    protected string $itemBId;
    protected string $warehouseAId;
    protected string $warehouseBId;
    protected string $documentAId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_LOOKUP_A',
            'status'      => 1,
        ]);
        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'INV_LOOKUP_B',
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
            'code'      => 'inv-lookup-mgr',
            'name'      => 'Lookup Manager',
            'status'    => 1,
        ]);

        foreach ([
            'inventory.location.view',
            'inventory.location.create',
            'inventory.stock-batch.view',
            'inventory.stock-batch.create',
            'inventory.document.view',
            'inventory.document.create',
            'inventory.document-item.view',
            'inventory.document-item.create',
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
            'lookup-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $itemA = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITM-LOOKUP-A',
            'name'             => 'Item A',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemAId = $itemA->item_id;

        $itemB = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantB->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITM-LOOKUP-B',
            'name'             => 'Item B',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemBId = $itemB->item_id;

        $whA = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-LOOKUP-A',
            'name'         => 'Warehouse A',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseAId = $whA->warehouse_id;

        $whB = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantB->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-LOOKUP-B',
            'name'         => 'Warehouse B',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseBId = $whB->warehouse_id;

        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'DOC-LOOKUP-1',
            'posting_date'     => now(),
            'status'           => 1,
            'row_version'      => 1,
        ]);
        $this->documentAId = $doc->document_id;
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->tokenA,
            'Accept'        => 'application/json',
            'X-Tenant-ID'   => $this->tenantA->tenant_id,
        ];
    }

    #[Test]
    public function item_lookup_require_active_rejects_other_tenant_item(): void
    {
        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);

        $lookup = app(ItemLookupService::class);

        $this->assertTrue($lookup->exists($this->itemAId));
        $this->assertFalse($lookup->exists($this->itemBId));

        $this->expectException(ValidationException::class);
        $lookup->requireActive($this->itemBId);
    }

    #[Test]
    public function warehouse_lookup_require_active_rejects_other_tenant_warehouse(): void
    {
        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);

        $lookup = app(WarehouseLookupService::class);

        $this->assertTrue($lookup->exists($this->warehouseAId));
        $this->assertFalse($lookup->exists($this->warehouseBId));

        $this->expectException(ValidationException::class);
        $lookup->requireActive($this->warehouseBId);
    }

    #[Test]
    public function location_create_rejects_warehouse_of_other_tenant(): void
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

    #[Test]
    public function stock_batch_create_rejects_item_of_other_tenant(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-batches', [
                'item_id'            => $this->itemBId,
                'batch_number'       => 'BATCH-X',
                'quantity_produced'  => '10',
                'quantity_remaining' => '10',
                'qc_status'          => 1,
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function document_item_create_rejects_item_of_other_tenant(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/document-items', [
                'document_id' => $this->documentAId,
                'item_id'     => $this->itemBId,
                'quantity'    => 1,
                'unit_cost'   => 0,
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function stock_batch_create_accepts_own_tenant_item(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-batches', [
                'item_id'            => $this->itemAId,
                'batch_number'       => 'BATCH-A-1',
                'quantity_produced'  => '10',
                'quantity_remaining' => '10',
                'qc_status'          => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }
}
