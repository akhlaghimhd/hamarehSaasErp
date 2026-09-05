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
use App\Modules\Inventory\Models\ItemBarcode;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-17 — Item barcodes owned by Inventory; CRUD + tenant isolation.
 */
class ItemBarcodeCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $tokenA;
    protected string $itemIdA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'BC_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'BC_B', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'bc-mgr',
            'name'      => 'Barcode Manager',
            'status'    => 1,
        ]);

        foreach ([
            'inventory.item-barcode.view',
            'inventory.item-barcode.create',
            'inventory.item-barcode.update',
            'inventory.item-barcode.delete',
        ] as $code) {
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
            'bc',
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
            'code' => 'ITEM-BC',
            'name' => 'Barcode Item',
            'item_type' => 1,
            'valuation_method' => 1,
            'status' => 1,
        ]);
        $this->itemIdA = $item->item_id;
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
    public function table_and_rls_exist(): void
    {
        $this->assertTrue(Schema::hasTable('inv_item_barcodes'));
        $this->assertTrue(Schema::hasColumn('inv_item_barcodes', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('inv_item_barcodes', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('inv_item_barcodes', 'row_version'));
    }

    #[Test]
    public function create_list_update_soft_delete_barcode(): void
    {
        $create = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/item-barcodes', [
                'item_id'      => $this->itemIdA,
                'barcode'      => '5901234123457',
                'barcode_type' => 'EAN13',
                'is_primary'   => true,
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.barcode', '5901234123457')
            ->assertJsonPath('data.is_primary', true);

        $id = $create->json('data.barcode_id');

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/item-barcodes?item_id=' . $this->itemIdA)
            ->assertStatus(200)
            ->assertJsonPath('data.0.barcode_id', $id);

        $this->withHeaders($this->authHeaders())
            ->putJson('/api/inventory/item-barcodes/' . $id, [
                'sku' => 'SKU-001',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.sku', 'SKU-001');

        $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/inventory/item-barcodes/' . $id)
            ->assertStatus(200);

        $this->assertSoftDeleted('inv_item_barcodes', ['barcode_id' => $id]);
    }

    #[Test]
    public function barcode_is_tenant_isolated(): void
    {
        $foreign = ItemBarcode::withoutGlobalScopes()->create([
            'barcode_id'   => (string) Str::uuid(),
            'tenant_id'    => $this->tenantB->tenant_id,
            'item_id'      => $this->itemIdA,
            'barcode'      => 'FOREIGN-BC-99',
            'barcode_type' => 'EAN13',
            'is_primary'   => false,
            'row_version'  => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/item-barcodes/' . $foreign->barcode_id)
            ->assertStatus(404);
    }

    #[Test]
    public function duplicate_barcode_in_same_tenant_rejected(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/item-barcodes', [
                'item_id' => $this->itemIdA,
                'barcode' => 'DUP-001',
            ])->assertStatus(201);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/item-barcodes', [
                'item_id' => $this->itemIdA,
                'barcode' => 'DUP-001',
            ])->assertStatus(422);
    }
}
