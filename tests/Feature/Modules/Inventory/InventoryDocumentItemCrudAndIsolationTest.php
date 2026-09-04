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
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Models\InventoryDocumentItem;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-05 — Document line items CRUD + draft-only mutation + tenant isolation
 */
class InventoryDocumentItemCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $tokenA;
    protected string $itemAId;
    protected string $locationAId;
    protected string $draftDocId;
    protected string $postedDocId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_DI_A',
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
            'code'      => 'inv-di-mgr',
            'name'      => 'Document Item Manager',
            'status'    => 1,
        ]);

        foreach ([
            'inventory.document-item.view',
            'inventory.document-item.create',
            'inventory.document-item.update',
            'inventory.document-item.delete',
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
            'di-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'INV_DI_B',
            'status'      => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $item = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITEM-DI-01',
            'name'             => 'DI Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemAId = $item->item_id;

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-DI-A',
            'name'         => 'DI Warehouse',
            'is_bonded'    => false,
            'status'       => 1,
        ]);

        $location = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-DI-01',
            'name'         => 'Bin DI 01',
            'status'       => 1,
        ]);
        $this->locationAId = $location->location_id;

        $draft = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-DI-DRAFT',
            'status'           => 1,
        ]);
        $this->draftDocId = $draft->document_id;

        $posted = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-DI-POSTED',
            'status'           => 3,
        ]);
        $this->postedDocId = $posted->document_id;
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
    public function can_create_list_show_update_and_delete_item_on_draft_document(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/document-items', [
                'document_id'    => $this->draftDocId,
                'item_id'        => $this->itemAId,
                'to_location_id' => $this->locationAId,
                'quantity'       => 10.5,
                'unit_cost'      => 100,
                'sort_order'     => 1,
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $lineId = $createResponse->json('data.document_item_id');
        $this->assertNotEmpty($lineId);

        // Generated total_cost = 10.5 * 100
        $this->assertEquals('1050.0000', (string) $createResponse->json('data.total_cost'));

        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/document-items?document_id=' . $this->draftDocId);

        $indexResponse->assertStatus(200);
        $ids = collect($indexResponse->json('data'))->pluck('document_item_id')->toArray();
        $this->assertContains($lineId, $ids);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/document-items/' . $lineId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.document_item_id', $lineId);

        $updateResponse = $this->withHeaders($this->authHeaders())
            ->putJson('/api/inventory/document-items/' . $lineId, [
                'quantity'  => 20,
                'unit_cost' => 50,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $updated = InventoryDocumentItem::withoutGlobalScopes()->find($lineId);
        $this->assertEquals('1000.0000', (string) $updated->total_cost);

        $deleteResponse = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/inventory/document-items/' . $lineId);

        $deleteResponse->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseMissing('inv_document_items', [
            'document_item_id' => $lineId,
        ]);
    }

    #[Test]
    public function cannot_mutate_items_on_posted_document(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/document-items', [
                'document_id'    => $this->postedDocId,
                'item_id'        => $this->itemAId,
                'to_location_id' => $this->locationAId,
                'quantity'       => 5,
                'unit_cost'      => 10,
            ]);

        $createResponse->assertStatus(409);

        $existing = InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id'      => $this->postedDocId,
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_id'          => $this->itemAId,
            'to_location_id'   => $this->locationAId,
            'quantity'         => 5,
            'unit_cost'        => 10,
            'sort_order'       => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->putJson('/api/inventory/document-items/' . $existing->document_item_id, [
                'quantity' => 99,
            ])
            ->assertStatus(409);

        $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/inventory/document-items/' . $existing->document_item_id)
            ->assertStatus(409);

        $this->assertDatabaseHas('inv_document_items', [
            'document_item_id' => $existing->document_item_id,
        ]);
    }

    #[Test]
    public function tenant_a_cannot_see_document_item_of_tenant_b(): void
    {
        $itemB = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantB->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITEM-DI-B',
            'name'             => 'DI Item B',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);

        $docB = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantB->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-B-ONLY',
            'status'           => 1,
        ]);

        $lineB = InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id'      => $docB->document_id,
            'tenant_id'        => $this->tenantB->tenant_id,
            'item_id'          => $itemB->item_id,
            'quantity'         => 1,
            'unit_cost'        => 1,
            'sort_order'       => 1,
        ]);

        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/document-items');

        $indexResponse->assertStatus(200);
        $ids = collect($indexResponse->json('data'))->pluck('document_item_id')->toArray();
        $this->assertNotContains($lineB->document_item_id, $ids);

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/document-items/' . $lineB->document_item_id)
            ->assertStatus(404);
    }
}
