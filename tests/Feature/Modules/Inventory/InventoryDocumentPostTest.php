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
 * L6-INV-06 — Post inventory document and update stock balances
 */
class InventoryDocumentPostTest extends TestCase
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
            'tenant_code' => 'INV_POST_A',
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
            'code'      => 'inv-post-mgr',
            'name'      => 'Post Manager',
            'status'    => 1,
        ]);

        foreach ([
            'inventory.document.view',
            'inventory.document.create',
            'inventory.document.update',
            'inventory.document.post',
            'inventory.document.delete',
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
            'post-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $item = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITEM-POST-01',
            'name'             => 'Post Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemId = $item->item_id;

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-POST',
            'name'         => 'Post WH',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseId = $warehouse->warehouse_id;

        $loc1 = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-P1',
            'name'         => 'Bin P1',
            'status'       => 1,
        ]);
        $this->locationId = $loc1->location_id;

        $loc2 = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-P2',
            'name'         => 'Bin P2',
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
    public function posting_receipt_increases_stock_balance(): void
    {
        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-POST-01',
            'status'           => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id'      => $doc->document_id,
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_id'          => $this->itemId,
            'to_location_id'   => $this->locationId,
            'quantity'         => 25,
            'unit_cost'        => 10,
            'sort_order'       => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 3);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals('25.0000', (string) $balance->quantity_on_hand);
        $this->assertEquals('25.0000', (string) $balance->quantity_available);
    }

    #[Test]
    public function posting_issue_decreases_stock_and_rejects_insufficient(): void
    {
        StockBalance::withoutGlobalScopes()->create([
            'stock_balance_id'  => (string) Str::uuid(),
            'tenant_id'         => $this->tenantA->tenant_id,
            'warehouse_id'      => $this->warehouseId,
            'location_id'       => $this->locationId,
            'item_id'           => $this->itemId,
            'quantity_on_hand'  => 10,
            'quantity_reserved' => 0,
            'row_version'       => 1,
            'updated_at'        => now(),
        ]);

        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 2,
            'document_number'  => 'GI-POST-01',
            'status'           => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id'      => $doc->document_id,
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_id'          => $this->itemId,
            'from_location_id' => $this->locationId,
            'quantity'         => 4,
            'unit_cost'        => 10,
            'sort_order'       => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post');

        $response->assertStatus(200)->assertJsonPath('data.status', 3);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();

        $this->assertEquals('6.0000', (string) $balance->quantity_on_hand);

        $doc2 = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 2,
            'document_number'  => 'GI-POST-02',
            'status'           => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id'      => $doc2->document_id,
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_id'          => $this->itemId,
            'from_location_id' => $this->locationId,
            'quantity'         => 100,
            'unit_cost'        => 10,
            'sort_order'       => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc2->document_id . '/post')
            ->assertStatus(409);
    }

    #[Test]
    public function cannot_post_empty_or_already_posted_document(): void
    {
        $empty = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-EMPTY',
            'status'           => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $empty->document_id . '/post')
            ->assertStatus(409);

        $posted = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-ALREADY',
            'status'           => 3,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id'      => $posted->document_id,
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_id'          => $this->itemId,
            'to_location_id'   => $this->locationId,
            'quantity'         => 1,
            'unit_cost'        => 1,
            'sort_order'       => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $posted->document_id . '/post')
            ->assertStatus(409);
    }
}
