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
use App\Modules\ProcurementSales\Models\PurchaseOrder;
use App\Modules\ProcurementSales\Models\PurchaseOrderItem;
use App\Modules\ProcurementSales\Services\PurchaseOrderService;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseOrderDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseOrderItemDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-PS-01 — Purchase Order create + tenant isolation
 */
class PurchaseOrderCreateAndIsolationTest extends TestCase
{
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'PS_PO_A',
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
            'code'      => 'ps-po-mgr',
            'name'      => 'PO Manager',
            'status'    => 1,
        ]);

        foreach ([
            'procurement.purchase-order.view',
            'procurement.purchase-order.create',
            'procurement.purchase-order.update',
            'procurement.purchase-order.delete',
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
            'ps-po-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'PS_PO_B',
            'status'      => 1,
        ]);

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
    public function can_create_purchase_order_with_items_via_service(): void
    {
        $service = app(PurchaseOrderService::class);

        $dto = new CreatePurchaseOrderDTO(
            supplierId: (string) Str::uuid(),
            currencyId: (string) Str::uuid(),
            orderDate: '2026-09-05',
            deliveryDate: '2026-09-20',
            items: [
                new PurchaseOrderItemDTO(
                    itemId: (string) Str::uuid(),
                    quantity: 10.0,
                    unitPrice: 100.0,
                    discountAmount: 0.0,
                    taxAmount: 9.0,
                    uomCode: 'PCS',
                    lineNumber: 1,
                ),
            ],
        );

        $order = $service->createPurchaseOrder($dto);

        $this->assertNotEmpty($order->purchase_order_id);
        $this->assertSame($this->tenantA->tenant_id, $order->tenant_id);
        $this->assertSame(PurchaseOrderService::STATUS_DRAFT, (int) $order->status);
        $this->assertEquals('1009.0000', (string) $order->total_amount);
        $this->assertCount(1, $order->items);
        $this->assertDatabaseHas('purchase_orders', [
            'purchase_order_id' => $order->purchase_order_id,
            'tenant_id'         => $this->tenantA->tenant_id,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $order->purchase_order_id,
            'tenant_id'         => $this->tenantA->tenant_id,
        ]);
    }

    #[Test]
    public function tenant_a_cannot_see_purchase_order_of_tenant_b(): void
    {
        $orderB = PurchaseOrder::withoutGlobalScopes()->create([
            'purchase_order_id' => (string) Str::uuid(),
            'tenant_id'         => $this->tenantB->tenant_id,
            'order_number'      => 'PO-B-ONLY',
            'supplier_id'       => (string) Str::uuid(),
            'order_date'        => '2026-09-05',
            'subtotal_amount'   => 50,
            'tax_amount'        => 0,
            'discount_amount'   => 0,
            'total_amount'      => 50,
            'status'            => 1,
            'currency_id'       => (string) Str::uuid(),
            'row_version'       => 1,
        ]);

        $visible = PurchaseOrder::query()->pluck('purchase_order_id')->toArray();
        $this->assertNotContains($orderB->purchase_order_id, $visible);

        $this->assertNull(
            PurchaseOrder::query()->where('purchase_order_id', $orderB->purchase_order_id)->first()
        );
    }

    #[Test]
    public function can_create_purchase_order_via_api(): void
    {
        $payload = [
            'supplier_id'   => (string) Str::uuid(),
            'currency_id'   => (string) Str::uuid(),
            'order_date'    => '2026-09-05',
            'delivery_date' => '2026-09-15',
            'items'         => [
                [
                    'item_id'    => (string) Str::uuid(),
                    'quantity'   => 2,
                    'unit_price' => 25.5,
                    'tax_amount' => 1.0,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/procurement-sales/purchase-orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Purchase Order created successfully');

        $orderId = $response->json('data.purchase_order_id');
        $this->assertNotEmpty($orderId);
        $this->assertDatabaseHas('purchase_orders', [
            'purchase_order_id' => $orderId,
            'tenant_id'         => $this->tenantA->tenant_id,
        ]);
    }
}
