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
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Models\InventoryDocumentItem;
use App\Modules\Inventory\Events\StockReservedV1;
use App\Modules\Inventory\Events\StockReservationReleasedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-13 — Soft stock reservation (quantity_reserved / quantity_available).
 */
class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected User $userA;
    protected string $tokenA;
    protected string $itemId;
    protected string $locationId;
    protected string $warehouseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_RSV_A',
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
            'code'      => 'inv-rsv-mgr',
            'name'      => 'Reservation Manager',
            'status'    => 1,
        ]);

        foreach ([
            'inventory.stock-reservation.reserve',
            'inventory.stock-reservation.release',
            'inventory.stock-balance.view',
            'inventory.document.view',
            'inventory.document.post',
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
            'inv-rsv',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $item = Item::withoutGlobalScopes()->create([
            'item_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'item_group_id' => (string) Str::uuid(),
            'uom_id' => (string) Str::uuid(),
            'code' => 'ITM-RSV',
            'name' => 'Reserve Item',
            'item_type' => 1,
            'valuation_method' => 1,
            'status' => 1,
        ]);
        $this->itemId = $item->item_id;

        $wh = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'branch_id' => (string) Str::uuid(),
            'code' => 'WH-RSV',
            'name' => 'WH Reserve',
            'is_bonded' => false,
            'status' => 1,
        ]);
        $this->warehouseId = $wh->warehouse_id;

        $loc = Location::withoutGlobalScopes()->create([
            'location_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'warehouse_id' => $wh->warehouse_id,
            'code' => 'BIN-RSV',
            'name' => 'Bin Reserve',
            'status' => 1,
        ]);
        $this->locationId = $loc->location_id;

        StockBalance::withoutGlobalScopes()->create([
            'stock_balance_id'  => (string) Str::uuid(),
            'tenant_id'         => $this->tenantA->tenant_id,
            'warehouse_id'      => $this->warehouseId,
            'location_id'       => $this->locationId,
            'item_id'           => $this->itemId,
            'quantity_on_hand'  => 20,
            'quantity_reserved' => 0,
            'row_version'       => 1,
        ]);
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
    public function reserve_increases_reserved_and_writes_outbox_event(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-reservations/reserve', [
                'location_id'          => $this->locationId,
                'item_id'              => $this->itemId,
                'quantity'             => 7,
                'source_document_type' => 'sales_order',
                'source_document_id'   => (string) Str::uuid(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();

        $this->assertEquals('7.0000', (string) $balance->quantity_reserved);
        $this->assertEquals('13.0000', (string) $balance->quantity_available);

        $event = DB::table('event_outbox')
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->where('event_type', StockReservedV1::EVENT_TYPE)
            ->first();
        $this->assertNotNull($event);
        $payload = json_decode($event->payload, true);
        $this->assertEquals(7.0, $payload['quantity']);
        $this->assertSame('sales_order', $payload['source_document_type']);
    }

    #[Test]
    public function reserve_rejects_when_available_insufficient(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-reservations/reserve', [
                'location_id' => $this->locationId,
                'item_id'     => $this->itemId,
                'quantity'    => 12,
            ])->assertStatus(200);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-reservations/reserve', [
                'location_id' => $this->locationId,
                'item_id'     => $this->itemId,
                'quantity'    => 10,
            ])->assertStatus(409);
    }

    #[Test]
    public function release_decreases_reserved(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-reservations/reserve', [
                'location_id' => $this->locationId,
                'item_id'     => $this->itemId,
                'quantity'    => 5,
            ])->assertStatus(200);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-reservations/release', [
                'location_id' => $this->locationId,
                'item_id'     => $this->itemId,
                'quantity'    => 3,
            ])->assertStatus(200);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();

        $this->assertEquals('2.0000', (string) $balance->quantity_reserved);
        $this->assertEquals('18.0000', (string) $balance->quantity_available);

        $event = DB::table('event_outbox')
            ->where('event_type', StockReservationReleasedV1::EVENT_TYPE)
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->first();
        $this->assertNotNull($event);
    }

    #[Test]
    public function issue_cannot_reduce_on_hand_below_reserved(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-reservations/reserve', [
                'location_id' => $this->locationId,
                'item_id'     => $this->itemId,
                'quantity'    => 15,
            ])->assertStatus(200);

        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type' => 2,
            'document_number' => 'GI-RSV-01',
            'status' => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id' => $doc->document_id,
            'tenant_id' => $this->tenantA->tenant_id,
            'item_id' => $this->itemId,
            'from_location_id' => $this->locationId,
            'quantity' => 10,
            'unit_cost' => 1,
            'sort_order' => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(409);
    }
}
