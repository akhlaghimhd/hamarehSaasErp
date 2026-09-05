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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-11 — Inventory post/void creates/reverses Accounting vouchers via VoucherPostingService.
 */
class InventoryAccountingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected User $userA;
    protected string $tokenA;
    protected string $itemId;
    protected string $locationId;
    protected string $accountAsset;
    protected string $accountClearing;
    protected string $accountCogs;
    protected string $accountAdj;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_ACC_A',
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
            'code'      => 'inv-acc-mgr',
            'name'      => 'Inv Acc Manager',
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
            'inv-acc',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $this->accountAsset    = (string) Str::uuid();
        $this->accountClearing = (string) Str::uuid();
        $this->accountCogs     = (string) Str::uuid();
        $this->accountAdj      = (string) Str::uuid();

        DB::table('fin_accounts')->insert([
            [
                'account_id' => $this->accountAsset, 'tenant_id' => $this->tenantA->tenant_id,
                'code' => '1200', 'name' => 'Inventory Asset', 'account_type' => 1, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
            [
                'account_id' => $this->accountClearing, 'tenant_id' => $this->tenantA->tenant_id,
                'code' => '2100', 'name' => 'GR/IR Clearing', 'account_type' => 2, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
            [
                'account_id' => $this->accountCogs, 'tenant_id' => $this->tenantA->tenant_id,
                'code' => '5100', 'name' => 'COGS', 'account_type' => 3, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
            [
                'account_id' => $this->accountAdj, 'tenant_id' => $this->tenantA->tenant_id,
                'code' => '5200', 'name' => 'Inventory Adjustment', 'account_type' => 3, 'level' => 1,
                'is_active' => true, 'created_at' => now(), 'row_version' => 1,
            ],
        ]);

        $item = Item::withoutGlobalScopes()->create([
            'item_id' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->tenant_id,
            'item_group_id' => (string) Str::uuid(), 'uom_id' => (string) Str::uuid(),
            'code' => 'ITM-ACC-1', 'name' => 'Acc Item', 'item_type' => 1,
            'valuation_method' => 1, 'status' => 1,
        ]);
        $this->itemId = $item->item_id;

        $wh = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->tenant_id,
            'branch_id' => (string) Str::uuid(), 'code' => 'WH-ACC', 'name' => 'WH Acc',
            'is_bonded' => false, 'status' => 1,
        ]);

        $loc = Location::withoutGlobalScopes()->create([
            'location_id' => (string) Str::uuid(), 'tenant_id' => $this->tenantA->tenant_id,
            'warehouse_id' => $wh->warehouse_id, 'code' => 'BIN-ACC', 'name' => 'Bin Acc', 'status' => 1,
        ]);
        $this->locationId = $loc->location_id;
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
    public function posting_receipt_creates_balanced_accounting_voucher(): void
    {
        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type' => 1,
            'document_number' => 'GR-ACC-01',
            'status' => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id' => $doc->document_id,
            'tenant_id' => $this->tenantA->tenant_id,
            'item_id' => $this->itemId,
            'to_location_id' => $this->locationId,
            'quantity' => 10,
            'unit_cost' => 15.5,
            'sort_order' => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post');

        $response->assertStatus(200)->assertJsonPath('data.status', 3);

        $doc->refresh();
        $this->assertNotNull($doc->accounting_voucher_id);

        $this->assertDatabaseHas('fin_vouchers', [
            'voucher_id' => $doc->accounting_voucher_id,
            'tenant_id'  => $this->tenantA->tenant_id,
            'reference_number' => 'INV-GR-ACC-01',
        ]);

        $amount = 155.0;
        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $doc->accounting_voucher_id,
            'account_id' => $this->accountAsset,
            'debit'      => $amount,
        ]);
        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $doc->accounting_voucher_id,
            'account_id' => $this->accountClearing,
            'credit'     => $amount,
        ]);
    }

    #[Test]
    public function posting_issue_creates_cogs_voucher(): void
    {
        StockBalance::withoutGlobalScopes()->create([
            'stock_balance_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'warehouse_id' => Location::withoutGlobalScopes()->find($this->locationId)->warehouse_id,
            'location_id' => $this->locationId,
            'item_id' => $this->itemId,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
            'row_version' => 1,
        ]);

        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type' => 2,
            'document_number' => 'GI-ACC-01',
            'status' => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id' => $doc->document_id,
            'tenant_id' => $this->tenantA->tenant_id,
            'item_id' => $this->itemId,
            'from_location_id' => $this->locationId,
            'quantity' => 4,
            'unit_cost' => 12.25,
            'sort_order' => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(200);

        $doc->refresh();
        $this->assertNotNull($doc->accounting_voucher_id);

        $amount = 49.0;
        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $doc->accounting_voucher_id,
            'account_id' => $this->accountCogs,
            'debit'      => $amount,
        ]);
        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $doc->accounting_voucher_id,
            'account_id' => $this->accountAsset,
            'credit'     => $amount,
        ]);
    }

    #[Test]
    public function voiding_receipt_creates_reversal_voucher(): void
    {
        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type' => 1,
            'document_number' => 'GR-ACC-VOID',
            'status' => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id' => $doc->document_id,
            'tenant_id' => $this->tenantA->tenant_id,
            'item_id' => $this->itemId,
            'to_location_id' => $this->locationId,
            'quantity' => 2,
            'unit_cost' => 100,
            'sort_order' => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(200);

        $doc->refresh();
        $this->assertNotNull($doc->accounting_voucher_id);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/void')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 4);

        $this->assertDatabaseHas('fin_vouchers', [
            'reference_number' => 'REV-INV-GR-ACC-VOID',
            'tenant_id' => $this->tenantA->tenant_id,
        ]);
    }

    #[Test]
    public function posting_without_gl_accounts_still_posts_stock(): void
    {
        DB::table('fin_accounts')->where('tenant_id', $this->tenantA->tenant_id)->delete();

        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type' => 1,
            'document_number' => 'GR-NO-GL',
            'status' => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id' => $doc->document_id,
            'tenant_id' => $this->tenantA->tenant_id,
            'item_id' => $this->itemId,
            'to_location_id' => $this->locationId,
            'quantity' => 3,
            'unit_cost' => 10,
            'sort_order' => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 3);

        $doc->refresh();
        $this->assertNull($doc->accounting_voucher_id);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();
        $this->assertNotNull($balance);
        $this->assertEquals('3.0000', (string) $balance->quantity_on_hand);
    }
}
