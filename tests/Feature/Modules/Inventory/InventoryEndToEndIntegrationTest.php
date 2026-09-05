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
use App\Modules\Inventory\Events\StockMovementPostedV1;
use App\Modules\Inventory\Events\InventoryDocumentPostedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-15 — End-to-end: Inventory master data → document post → stock → Accounting voucher → void.
 */
class InventoryEndToEndIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected User $userA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_E2E_A',
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
            'code'      => 'inv-e2e-mgr',
            'name'      => 'Inv E2E Manager',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'master-data.item.view',
            'master-data.item.create',
            'master-data.warehouse.view',
            'master-data.warehouse.create',
            'inventory.location.view',
            'inventory.location.create',
            'inventory.document.view',
            'inventory.document.create',
            'inventory.document.post',
            'inventory.document.void',
            'inventory.document-item.view',
            'inventory.document-item.create',
            'inventory.stock-balance.view',
            'inventory.stock-reservation.reserve',
            'inventory.stock-reservation.release',
        ];

        foreach ($permissionCodes as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenantA->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Inventory',
                'action_type'          => 'EXECUTE',
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
            'inv-e2e',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        foreach (
            [
                ['1200', 'Inventory Asset', 1],
                ['2100', 'GR/IR Clearing', 2],
                ['5100', 'COGS', 3],
                ['5200', 'Inventory Adjustment', 3],
            ] as [$code, $name, $type]
        ) {
            DB::table('fin_accounts')->insert([
                'account_id'   => (string) Str::uuid(),
                'tenant_id'    => $this->tenantA->tenant_id,
                'code'         => $code,
                'name'         => $name,
                'account_type' => $type,
                'level'        => 1,
                'is_active'    => true,
                'created_at'   => now(),
                'row_version'  => 1,
            ]);
        }
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
    public function receipt_post_updates_stock_creates_voucher_and_outbox_events(): void
    {
        $item = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'E2E-ITEM',
            'name'             => 'E2E Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);

        $wh = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'E2E-WH',
            'name'         => 'E2E Warehouse',
            'is_bonded'    => false,
            'status'       => 1,
        ]);

        $loc = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $wh->warehouse_id,
            'code'         => 'E2E-BIN',
            'name'         => 'E2E Bin',
            'status'       => 1,
        ]);

        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-E2E-01',
            'status'           => 1,
            'description'      => 'E2E receipt',
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id'      => $doc->document_id,
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_id'          => $item->item_id,
            'to_location_id'   => $loc->location_id,
            'quantity'         => 10,
            'unit_cost'        => 25.5,
            'sort_order'       => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 3);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $loc->location_id)
            ->where('item_id', $item->item_id)
            ->first();
        $this->assertNotNull($balance);
        $this->assertEquals('10.0000', (string) $balance->quantity_on_hand);
        $this->assertEquals('10.0000', (string) $balance->quantity_available);

        $doc->refresh();
        $this->assertNotNull($doc->accounting_voucher_id);

        $this->assertDatabaseHas('fin_vouchers', [
            'voucher_id'       => $doc->accounting_voucher_id,
            'tenant_id'        => $this->tenantA->tenant_id,
            'reference_number' => 'INV-GR-E2E-01',
        ]);

        $amount = 255.0;
        $voucherItems = DB::table('fin_voucher_items')
            ->where('voucher_id', $doc->accounting_voucher_id)
            ->get();
        $this->assertCount(2, $voucherItems);
        $this->assertEqualsWithDelta($amount, (float) $voucherItems->sum('debit'), 0.0001);
        $this->assertEqualsWithDelta($amount, (float) $voucherItems->sum('credit'), 0.0001);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'    => $this->tenantA->tenant_id,
            'event_type'   => InventoryDocumentPostedV1::EVENT_TYPE,
            'aggregate_id' => $doc->document_id,
        ]);
        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'    => $this->tenantA->tenant_id,
            'event_type'   => StockMovementPostedV1::EVENT_TYPE,
            'aggregate_id' => $doc->document_id,
        ]);
    }

    #[Test]
    public function full_cycle_with_reservation_release_before_void(): void
    {
        $item = Item::withoutGlobalScopes()->create([
            'item_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'item_group_id' => (string) Str::uuid(),
            'uom_id' => (string) Str::uuid(),
            'code' => 'E2E-ITEM-2',
            'name' => 'E2E Item 2',
            'item_type' => 1,
            'valuation_method' => 1,
            'status' => 1,
        ]);

        $wh = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'branch_id' => (string) Str::uuid(),
            'code' => 'E2E-WH-2',
            'name' => 'E2E WH 2',
            'is_bonded' => false,
            'status' => 1,
        ]);

        $loc = Location::withoutGlobalScopes()->create([
            'location_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'warehouse_id' => $wh->warehouse_id,
            'code' => 'E2E-BIN-2',
            'name' => 'E2E Bin 2',
            'status' => 1,
        ]);

        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type' => 1,
            'document_number' => 'GR-E2E-02',
            'status' => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id' => $doc->document_id,
            'tenant_id' => $this->tenantA->tenant_id,
            'item_id' => $item->item_id,
            'to_location_id' => $loc->location_id,
            'quantity' => 8,
            'unit_cost' => 12,
            'sort_order' => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(200);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-reservations/reserve', [
                'location_id' => $loc->location_id,
                'item_id' => $item->item_id,
                'quantity' => 3,
            ])->assertStatus(200);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/stock-reservations/release', [
                'location_id' => $loc->location_id,
                'item_id' => $item->item_id,
                'quantity' => 3,
            ])->assertStatus(200);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/void')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 4);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $loc->location_id)
            ->where('item_id', $item->item_id)
            ->first();
        $this->assertNotNull($balance);
        $this->assertEquals('0.0000', (string) $balance->quantity_on_hand);
        $this->assertEquals('0.0000', (string) $balance->quantity_reserved);

        $this->assertDatabaseHas('fin_vouchers', [
            'tenant_id' => $this->tenantA->tenant_id,
            'reference_number' => 'REV-INV-GR-E2E-02',
        ]);
    }
}
