<?php

namespace Tests\Feature\Modules\ProcurementSales;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseRequisitionDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseRequisitionItemDTO;
use App\Modules\ProcurementSales\Services\PurchaseRequisitionService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PurchaseRequisitionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $departmentId;
    protected string $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['tenant_code' => 'PR_A', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        Context::add('tenant_id', $this->tenant->tenant_id);
        Context::add('user_id', $this->user->user_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
        ScopeContext::resetInstance();
        $this->departmentId = (string) Str::uuid();
        $this->itemId = (string) Str::uuid();
    }

    #[Test]
    public function can_create_submit_approve_requisition(): void
    {
        $svc = app(PurchaseRequisitionService::class);
        $req = $svc->create(new CreatePurchaseRequisitionDTO(
            departmentId: $this->departmentId,
            requiredDate: now()->addDays(7)->toDateString(),
            priority: 1,
            description: 'Need materials',
            items: [
                new PurchaseRequisitionItemDTO(itemId: $this->itemId, quantity: 10, estimatedUnitPrice: 25.5),
            ],
        ));
        $this->assertSame(PurchaseRequisitionService::STATUS_DRAFT, (int) $req->status);
        $this->assertCount(1, $req->items);

        $submitted = $svc->submit($req->requisition_id);
        $this->assertSame(PurchaseRequisitionService::STATUS_PENDING, (int) $submitted->status);

        $approved = $svc->approve($submitted->requisition_id);
        $this->assertSame(PurchaseRequisitionService::STATUS_APPROVED, (int) $approved->status);
    }

    #[Test]
    public function reject_pending_goes_to_rejected(): void
    {
        $svc = app(PurchaseRequisitionService::class);
        $req = $svc->create(new CreatePurchaseRequisitionDTO(
            departmentId: $this->departmentId,
            requiredDate: now()->toDateString(),
            items: [new PurchaseRequisitionItemDTO(itemId: $this->itemId, quantity: 1)],
        ));
        $svc->submit($req->requisition_id);
        $rejected = $svc->reject($req->requisition_id);
        $this->assertSame(PurchaseRequisitionService::STATUS_REJECTED, (int) $rejected->status);
    }

    #[Test]
    public function cannot_approve_draft(): void
    {
        $svc = app(PurchaseRequisitionService::class);
        $req = $svc->create(new CreatePurchaseRequisitionDTO(
            departmentId: $this->departmentId,
            requiredDate: now()->toDateString(),
            items: [new PurchaseRequisitionItemDTO(itemId: $this->itemId, quantity: 1)],
        ));
        $this->expectException(ConflictHttpException::class);
        $svc->approve($req->requisition_id);
    }
}
