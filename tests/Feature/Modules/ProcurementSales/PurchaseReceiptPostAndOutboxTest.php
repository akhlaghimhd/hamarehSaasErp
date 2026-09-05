<?php

namespace Tests\Feature\Modules\ProcurementSales;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\ProcurementSales\Models\PurchaseReceipt;
use App\Modules\ProcurementSales\Services\PurchaseReceiptService;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseReceiptDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseReceiptItemDTO;
use App\Modules\ProcurementSales\Events\PurchaseReceiptPostedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-PS-03/04 — Purchase Receipt create, post, outbox boundary event
 */
class PurchaseReceiptPostAndOutboxTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'PS_PR_A',
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
            'code'      => 'ps-pr-mgr',
            'name'      => 'PR Manager',
            'status'    => 1,
        ]);

        foreach ([
            'procurement.purchase-receipt.view',
            'procurement.purchase-receipt.create',
            'procurement.purchase-receipt.post',
        ] as $code) {
            $parts = explode('.', $code);
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenantA->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'ProcurementSales',
                'action_type'          => strtoupper($parts[2] ?? 'VIEW'),
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
            'ps-pr-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        Context::add('tenant_id', $this->tenantA->tenant_id);
        Context::add('user_id', $this->userA->user_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();
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
    public function can_create_and_post_purchase_receipt_and_write_outbox_event(): void
    {
        $service = app(PurchaseReceiptService::class);

        $poId = (string) Str::uuid();
        $supplierId = (string) Str::uuid();
        $warehouseId = (string) Str::uuid();
        $itemId = (string) Str::uuid();

        $dto = new CreatePurchaseReceiptDTO(
            purchaseOrderId: $poId,
            supplierId: $supplierId,
            warehouseId: $warehouseId,
            receiptDate: '2026-09-05',
            items: [
                new PurchaseReceiptItemDTO(
                    itemId: $itemId,
                    receivedQuantity: 5.0,
                    unitPrice: 20.0,
                    orderedQuantity: 5.0,
                    lineNumber: 1,
                ),
            ],
        );

        $receipt = $service->createReceipt($dto);

        $this->assertNotEmpty($receipt->purchase_receipt_id);
        $this->assertSame(PurchaseReceiptService::STATUS_DRAFT, (int) $receipt->status);
        $this->assertSame($warehouseId, $receipt->warehouse_id);
        $this->assertCount(1, $receipt->items);

        $posted = $service->postReceipt($receipt->purchase_receipt_id);

        $this->assertSame(PurchaseReceiptService::STATUS_POSTED, (int) $posted->status);

        $outbox = DB::table('event_outbox')
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->where('aggregate_id', $receipt->purchase_receipt_id)
            ->where('event_type', PurchaseReceiptPostedV1::EVENT_TYPE)
            ->first();

        $this->assertNotNull($outbox);
        $payload = json_decode($outbox->payload, true);
        $this->assertSame($receipt->purchase_receipt_id, $payload['purchase_receipt_id']);
        $this->assertSame($warehouseId, $payload['warehouse_id']);
        $this->assertSame($supplierId, $payload['supplier_id']);
        $this->assertCount(1, $payload['lines']);
        $this->assertSame($itemId, $payload['lines'][0]['item_id']);
    }

    #[Test]
    public function cannot_post_already_posted_receipt(): void
    {
        $service = app(PurchaseReceiptService::class);

        $dto = new CreatePurchaseReceiptDTO(
            purchaseOrderId: (string) Str::uuid(),
            supplierId: (string) Str::uuid(),
            warehouseId: (string) Str::uuid(),
            receiptDate: '2026-09-05',
            items: [
                new PurchaseReceiptItemDTO(
                    itemId: (string) Str::uuid(),
                    receivedQuantity: 1.0,
                    unitPrice: 10.0,
                ),
            ],
        );

        $receipt = $service->createReceipt($dto);
        $service->postReceipt($receipt->purchase_receipt_id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
        $service->postReceipt($receipt->purchase_receipt_id);
    }

    #[Test]
    public function can_create_and_post_purchase_receipt_via_api(): void
    {
        $payload = [
            'purchase_order_id' => (string) Str::uuid(),
            'supplier_id'       => (string) Str::uuid(),
            'warehouse_id'      => (string) Str::uuid(),
            'receipt_date'      => '2026-09-05',
            'items'             => [
                [
                    'item_id'            => (string) Str::uuid(),
                    'received_quantity'  => 3,
                    'unit_price'         => 15.5,
                ],
            ],
        ];

        $create = $this->withHeaders($this->authHeaders())
            ->postJson('/api/procurement-sales/purchase-receipts', $payload);

        $create->assertStatus(201);
        $receiptId = $create->json('data.purchase_receipt_id');
        $this->assertNotEmpty($receiptId);

        $post = $this->withHeaders($this->authHeaders())
            ->postJson('/api/procurement-sales/purchase-receipts/' . $receiptId . '/post');

        $post->assertStatus(200)
            ->assertJsonPath('data.status', PurchaseReceiptService::STATUS_POSTED);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'    => $this->tenantA->tenant_id,
            'aggregate_id' => $receiptId,
            'event_type'   => PurchaseReceiptPostedV1::EVENT_TYPE,
        ]);
    }
}
