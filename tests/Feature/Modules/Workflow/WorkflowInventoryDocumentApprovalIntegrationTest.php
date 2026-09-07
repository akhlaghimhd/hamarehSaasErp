<?php

namespace Tests\Feature\Modules\Workflow;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Models\InventoryDocumentItem;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\InventoryDocumentService;
use App\Modules\Workflow\Services\WorkflowEngineService;
use App\Modules\Workflow\Events\WorkflowTaskCompletedV1;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-19 — Inventory document submitForApproval → complete task → Posted (approve) or Draft (reject).
 */
class WorkflowInventoryDocumentApprovalIntegrationTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;
    protected string $roleId;
    protected string $itemId;
    protected string $locationId;
    protected string $warehouseId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'WF_INV_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);
        $this->roleId = (string) Str::uuid();

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        Context::add('tenant_id', $this->tenantA->tenant_id);
        Context::add('user_id', $this->userA->user_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $item = Item::withoutGlobalScopes()->create([
            'item_id'          => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_group_id'    => (string) Str::uuid(),
            'uom_id'           => (string) Str::uuid(),
            'code'             => 'ITEM-WF-01',
            'name'             => 'WF Item',
            'item_type'        => 1,
            'valuation_method' => 1,
            'status'           => 1,
        ]);
        $this->itemId = $item->item_id;

        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'warehouse_id' => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'branch_id'    => (string) Str::uuid(),
            'code'         => 'WH-WF',
            'name'         => 'WF WH',
            'is_bonded'    => false,
            'status'       => 1,
        ]);
        $this->warehouseId = $warehouse->warehouse_id;

        $loc = Location::withoutGlobalScopes()->create([
            'location_id'  => (string) Str::uuid(),
            'tenant_id'    => $this->tenantA->tenant_id,
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => 'BIN-WF',
            'name'         => 'Bin WF',
            'status'       => 1,
        ]);
        $this->locationId = $loc->location_id;
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    private function seedDefinition(WorkflowEngineService $engine): void
    {
        $engine->upsertDefinition(
            code: 'INVENTORY_DOCUMENT_APPROVAL_V1',
            name: 'Inventory Document Approval',
            targetAggregateType: 'inventory_documents',
            flowGraph: [
                'initial_state' => 'pending_approval',
                'states' => [
                    'pending_approval' => [
                        'task_name'      => 'Approve Inventory Document',
                        'assigned_type'  => WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
                        'assigned_to_id' => $this->roleId,
                        'on_approve'     => 'approved',
                        'on_reject'      => 'rejected',
                    ],
                    'approved' => ['terminal' => true],
                    'rejected' => ['terminal' => true],
                ],
            ],
        );
    }

    private function createDraftReceipt(): InventoryDocument
    {
        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => InventoryDocumentService::TYPE_RECEIPT,
            'document_number'  => 'GR-WF-' . Str::random(6),
            'posting_date'     => now(),
            'status'           => InventoryDocumentService::STATUS_DRAFT,
            'created_by'       => $this->userA->user_id,
            'row_version'      => 1,
        ]);

        InventoryDocumentItem::withoutGlobalScopes()->create([
            'document_item_id' => (string) Str::uuid(),
            'document_id'      => $doc->document_id,
            'tenant_id'        => $this->tenantA->tenant_id,
            'item_id'          => $this->itemId,
            'to_location_id'   => $this->locationId,
            'quantity'         => 12,
            'unit_cost'        => 8,
            'sort_order'       => 1,
        ]);

        return $doc->fresh(['items']);
    }

    #[Test]
    public function approve_workflow_posts_inventory_document_and_updates_stock(): void
    {
        $engine = app(WorkflowEngineService::class);
        $docService = app(InventoryDocumentService::class);

        $this->seedDefinition($engine);

        $doc = $this->createDraftReceipt();

        $pending = $docService->submitForApproval($doc->document_id);
        $this->assertSame(InventoryDocumentService::STATUS_PENDING, (int) $pending->status);

        $tasks = $engine->listPendingTasks(
            WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
            $this->roleId,
        );
        $this->assertCount(1, $tasks);

        $instance = $engine->completeTask($tasks->first()->task_id, true);
        $this->assertSame(WorkflowEngineService::INSTANCE_COMPLETED, (int) $instance->status);

        $payload = [
            'event_type'            => WorkflowTaskCompletedV1::EVENT_TYPE,
            'tenant_id'             => $this->tenantA->tenant_id,
            'task_id'               => $tasks->first()->task_id,
            'process_instance_id'   => $instance->process_instance_id,
            'target_aggregate_type' => 'inventory_documents',
            'target_aggregate_id'   => $doc->document_id,
            'previous_state'        => 'pending_approval',
            'current_state'         => 'approved',
            'approved'              => true,
            'instance_status'       => WorkflowEngineService::INSTANCE_COMPLETED,
            'actioned_by'           => $this->userA->user_id,
        ];
        event(WorkflowTaskCompletedV1::EVENT_TYPE, [$payload]);

        $doc->refresh();
        $this->assertSame(InventoryDocumentService::STATUS_POSTED, (int) $doc->status);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(12.0, (float) $balance->quantity_on_hand);
    }

    #[Test]
    public function reject_workflow_returns_inventory_document_to_draft(): void
    {
        $engine = app(WorkflowEngineService::class);
        $docService = app(InventoryDocumentService::class);

        $this->seedDefinition($engine);

        $doc = $this->createDraftReceipt();

        $pending = $docService->submitForApproval($doc->document_id);
        $this->assertSame(InventoryDocumentService::STATUS_PENDING, (int) $pending->status);

        $tasks = $engine->listPendingTasks(
            WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
            $this->roleId,
        );
        $this->assertCount(1, $tasks);

        $instance = $engine->completeTask($tasks->first()->task_id, false);
        $this->assertSame(WorkflowEngineService::INSTANCE_TERMINATED, (int) $instance->status);

        $payload = [
            'event_type'            => WorkflowTaskCompletedV1::EVENT_TYPE,
            'tenant_id'             => $this->tenantA->tenant_id,
            'task_id'               => $tasks->first()->task_id,
            'process_instance_id'   => $instance->process_instance_id,
            'target_aggregate_type' => 'inventory_documents',
            'target_aggregate_id'   => $doc->document_id,
            'previous_state'        => 'pending_approval',
            'current_state'         => 'rejected',
            'approved'              => false,
            'instance_status'       => WorkflowEngineService::INSTANCE_TERMINATED,
            'actioned_by'           => $this->userA->user_id,
        ];
        event(WorkflowTaskCompletedV1::EVENT_TYPE, [$payload]);

        $doc->refresh();
        $this->assertSame(InventoryDocumentService::STATUS_DRAFT, (int) $doc->status);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->first();
        $this->assertNull($balance);
    }
}
