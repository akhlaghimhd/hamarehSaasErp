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
use App\Modules\Inventory\Events\StockMovementPostedV1;
use App\Modules\Inventory\Events\InventoryDocumentPostedV1;
use App\Modules\Inventory\Events\InventoryDocumentVoidedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-12 — Versioned domain/integration events via transactional outbox.
 */
class InventoryOutboxEventsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected User $userA;
    protected string $tokenA;
    protected string $itemId;
    protected string $locationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_EVT_A',
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
            'code'      => 'inv-evt-mgr',
            'name'      => 'Inv Event Manager',
            'status'    => 1,
        ]);

        foreach (['inventory.document.view', 'inventory.document.post', 'inventory.document.void'] as $code) {
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
            'inv-evt',
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
            'code' => 'ITM-EVT',
            'name' => 'Event Item',
            'item_type' => 1,
            'valuation_method' => 1,
            'status' => 1,
        ]);
        $this->itemId = $item->item_id;

        $wh = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'branch_id' => (string) Str::uuid(),
            'code' => 'WH-EVT',
            'name' => 'WH Event',
            'is_bonded' => false,
            'status' => 1,
        ]);

        $loc = Location::withoutGlobalScopes()->create([
            'location_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'warehouse_id' => $wh->warehouse_id,
            'code' => 'BIN-EVT',
            'name' => 'Bin Event',
            'status' => 1,
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
    public function posting_document_writes_posted_and_stock_movement_outbox_events(): void
    {
        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type' => 1,
            'document_number' => 'GR-EVT-01',
            'status' => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id' => $doc->document_id,
            'tenant_id' => $this->tenantA->tenant_id,
            'item_id' => $this->itemId,
            'to_location_id' => $this->locationId,
            'quantity' => 5,
            'unit_cost' => 10,
            'sort_order' => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(200);

        $posted = DB::table('event_outbox')
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->where('event_type', InventoryDocumentPostedV1::EVENT_TYPE)
            ->where('aggregate_id', $doc->document_id)
            ->first();

        $this->assertNotNull($posted);
        $this->assertSame(InventoryDocumentPostedV1::AGGREGATE_TYPE, $posted->aggregate_type);
        $this->assertSame(1, (int) $posted->status);

        $movement = DB::table('event_outbox')
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->where('event_type', StockMovementPostedV1::EVENT_TYPE)
            ->where('aggregate_id', $doc->document_id)
            ->first();

        $this->assertNotNull($movement);
        $payload = json_decode($movement->payload, true);
        $this->assertSame($doc->document_id, $payload['document_id']);
        $this->assertSame(1, $payload['document_type']);
        $this->assertCount(1, $payload['lines']);
        $this->assertSame($this->itemId, $payload['lines'][0]['item_id']);
        $this->assertEquals(5.0, $payload['lines'][0]['quantity']);
        $this->assertSame($this->locationId, $payload['lines'][0]['to_location_id']);
    }

    #[Test]
    public function voiding_document_writes_voided_outbox_event_with_lines(): void
    {
        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type' => 1,
            'document_number' => 'GR-EVT-VOID',
            'status' => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id' => $doc->document_id,
            'tenant_id' => $this->tenantA->tenant_id,
            'item_id' => $this->itemId,
            'to_location_id' => $this->locationId,
            'quantity' => 2,
            'unit_cost' => 8,
            'sort_order' => 1,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/post')
            ->assertStatus(200);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents/' . $doc->document_id . '/void')
            ->assertStatus(200);

        $voided = DB::table('event_outbox')
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->where('event_type', InventoryDocumentVoidedV1::EVENT_TYPE)
            ->where('aggregate_id', $doc->document_id)
            ->first();

        $this->assertNotNull($voided);
        $payload = json_decode($voided->payload, true);
        $this->assertSame(4, $payload['status']);
        $this->assertCount(1, $payload['lines']);
        $this->assertSame($this->itemId, $payload['lines'][0]['item_id']);
    }
}
