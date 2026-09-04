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
use App\Modules\Inventory\Models\StockBalance;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-10 — Void posted inventory document and reverse stock balances
 */
class InventoryDocumentVoidTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected User $userA;
    protected string $tokenA;
    protected string $itemId;
    protected string $locationId;
    protected string $locationId2;
    protected string $warehouseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_VOID_A',
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
            'code'      => 'inv-void-mgr',
            'name'      => 'Void Manager',
            'status'    => 1,
        ]);

        foreach ([
            'inventory.document.view',
            'inventory.document.create',
            'inventory.document.post',
            'inventory.document.void',
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
            'void-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $item = Item::withoutGlobalScopes()->create([
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITEM-VOID-01',
            'name'             => 'Void Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemId = $item->item_id;

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'branch_id' => (string) Str::uuid(),
            'code'      => 'WH-VOID',
            'name'      => 'Void WH',
            'is_bonded' => false,
            'status'    => 1,
        ]);
        $this->warehouseId = $warehouse->warehouse_id;

        $loc1 = Location::withoutGlobalScopes()->create([
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $this->warehouseId,
            'code'         => 'BIN-V1',
            'name'         => 'Bin V1',
            'status'       => 1,
        ]);
        $this->locationId = $loc1->location_id;

        $loc2 = Location::withoutGlobalScopes()->create([
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $this->warehouseId,
            'code'         => 'BIN-V2',
            'name'         => 'Bin V2',
            'status'       => 1,
        ]);
        $this->locationId2 = $loc2->location_id;
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
    public function voiding_posted_receipt_reverses_stock_balance(): void
    {
        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-VOID-01',
            'status'           => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_id'    => $doc->document_id,
            'tenant_id'      => $this->tenantA->tenant_id,
            'item_id'        => $this->itemId,
            'to_location_id' => $this->locationId,
            'quantity'       => 30,
            'unit_cost'      => 10,
            'sort_order'     => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 3);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();
        $this->assertEquals('30.0000', (string) $balance->quantity_on_hand);

        $voidResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/void');

        $voidResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 4);

        $balance->refresh();
        $this->assertEquals('0.0000', (string) $balance->quantity_on_hand);

        $this->assertDatabaseHas('event_outbox', [
            'aggregate_id' => $doc->document_id,
            'event_type'   => 'inventory.document.voided.v1',
        ]);
    }

    #[Test]
    public function voiding_posted_transfer_restores_both_locations(): void
    {
        StockBalance::withoutGlobalScopes()->create([
            'tenant_id'         => $this->tenantA->tenant_id,
            'warehouse_id'      => $this->warehouseId,
            'location_id'       => $this->locationId,
            'item_id'           => $this->itemId,
            'quantity_on_hand'  => 20,
            'quantity_reserved' => 0,
            'row_version'       => 1,
            'updated_at'        => now(),
        ]);

        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 3,
            'document_number'  => 'TR-VOID-01',
            'status'           => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_id'      => $doc->document_id,
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_id'          => $this->itemId,
            'from_location_id' => $this->locationId,
            'to_location_id'   => $this->locationId2,
            'quantity'         => 8,
            'unit_cost'        => 5,
            'sort_order'       => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(200);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/void')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 4);

        $from = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();
        $to = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId2)
            ->where('item_id', $this->itemId)
            ->first();

        $this->assertEquals('20.0000', (string) $from->quantity_on_hand);
        $this->assertEquals('0.0000', (string) $to->quantity_on_hand);
    }

    #[Test]
    public function cannot_void_draft_or_already_voided_document(): void
    {
        $draft = InventoryDocument::withoutGlobalScopes()->create([
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-DRAFT-VOID',
            'status'           => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_id'    => $draft->document_id,
            'tenant_id'      => $this->tenantA->tenant_id,
            'item_id'        => $this->itemId,
            'to_location_id' => $this->locationId,
            'quantity'       => 1,
            'unit_cost'      => 1,
            'sort_order'     => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $draft->document_id . '/void')
            ->assertStatus(409);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $draft->document_id . '/post')
            ->assertStatus(200);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $draft->document_id . '/void')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 4);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $draft->document_id . '/void')
            ->assertStatus(409);
    }

    #[Test]
    public function void_rejects_when_reversed_stock_would_go_negative(): void
    {
        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-VOID-NEG',
            'status'           => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_id'    => $doc->document_id,
            'tenant_id'      => $this->tenantA->tenant_id,
            'item_id'        => $this->itemId,
            'to_location_id' => $this->locationId,
            'quantity'       => 10,
            'unit_cost'      => 1,
            'sort_order'     => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(200);

        // Consume stock after receipt so void cannot fully reverse
        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();
        $balance->update(['quantity_on_hand' => 3, 'updated_at' => now()]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/void')
            ->assertStatus(409);

        $this->assertDatabaseHas('inv_documents', [
            'document_id' => $doc->document_id,
            'status'      => 3,
        ]);
    }
}
