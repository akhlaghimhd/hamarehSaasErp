<?php

namespace Tests\Feature\Modules\ProcurementSales;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseRequisitionDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseRequisitionItemDTO;
use App\Modules\ProcurementSales\Services\PurchaseRequisitionService;
use App\Modules\ProcurementSales\Services\PurchaseOrderService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RequisitionToPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $departmentId;
    protected string $itemId;
    protected string $supplierId;
    protected string $currencyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['tenant_code' => 'REQ_PO', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        Context::add('tenant_id', $this->tenant->tenant_id);
        Context::add('user_id', $this->user->user_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
        ScopeContext::resetInstance();
        $this->departmentId = (string) Str::uuid();
        $this->itemId = (string) Str::uuid();
        $this->supplierId = (string) Str::uuid();
        $this->currencyId = (string) Str::uuid();
    }

    private function approvedRequisition(): string
    {
        $svc = app(PurchaseRequisitionService::class);
        $req = $svc->create(new CreatePurchaseRequisitionDTO(
            departmentId: $this->departmentId,
            requiredDate: now()->addDays(5)->toDateString(),
            priority: 2,
            description: 'Convert me',
            items: [
                new PurchaseRequisitionItemDTO(itemId: $this->itemId, quantity: 3, estimatedUnitPrice: 12.5),
            ],
        ));
        $svc->submit($req->requisition_id);
        $svc->approve($req->requisition_id);
        return $req->requisition_id;
    }

    #[Test]
    public function approved_requisition_converts_to_draft_po(): void
    {
        $id = $this->approvedRequisition();
        $svc = app(PurchaseRequisitionService::class);
        $order = $svc->convertToPurchaseOrder($id, $this->supplierId, $this->currencyId);

        $this->assertSame($this->supplierId, $order->supplier_id);
        $this->assertSame($id, $order->source_requisition_id);
        $this->assertCount(1, $order->items);
        $this->assertEquals(3.0, (float) $order->items->first()->quantity);
        $this->assertEquals(12.5, (float) $order->items->first()->unit_price);
        $this->assertSame(PurchaseOrderService::STATUS_DRAFT, (int) $order->status);
    }

    #[Test]
    public function cannot_convert_draft_or_twice(): void
    {
        $svc = app(PurchaseRequisitionService::class);
        $req = $svc->create(new CreatePurchaseRequisitionDTO(
            departmentId: $this->departmentId,
            requiredDate: now()->toDateString(),
            items: [new PurchaseRequisitionItemDTO(itemId: $this->itemId, quantity: 1, estimatedUnitPrice: 10)],
        ));
        $this->expectException(ConflictHttpException::class);
        $svc->convertToPurchaseOrder($req->requisition_id, $this->supplierId, $this->currencyId);
    }

    #[Test]
    public function cannot_convert_same_requisition_twice(): void
    {
        $id = $this->approvedRequisition();
        $svc = app(PurchaseRequisitionService::class);
        $svc->convertToPurchaseOrder($id, $this->supplierId, $this->currencyId);
        $this->expectException(ConflictHttpException::class);
        $svc->convertToPurchaseOrder($id, $this->supplierId, $this->currencyId);
    }
}
