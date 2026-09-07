<?php

namespace App\Modules\ProcurementSales\Listeners;

use App\Modules\ProcurementSales\Services\PurchaseOrderService;
use App\Modules\ProcurementSales\Services\SalesOrderService;
use App\Modules\Workflow\Events\WorkflowTaskCompletedV1;
use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * L6-WF-04 – When workflow completes for Sales/Purchase orders, apply domain transition.
 * Consumes workflow.task.completed.v1 (via Event::listen or Outbox processor).
 *
 * - sales_orders: approve → confirm; reject → draft
 * - purchase_orders: approve → sent; reject → draft
 */
class WorkflowTaskCompletedListener
{
    public function __construct(
        private readonly SalesOrderService $salesOrderService,
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        try {
            $aggregateType = $payload['target_aggregate_type'] ?? null;
            $aggregateId = $payload['target_aggregate_id'] ?? null;
            $approved = (bool) ($payload['approved'] ?? false);
            $instanceStatus = (int) ($payload['instance_status'] ?? 0);

            if (empty($aggregateId)) {
                return;
            }

            // Only act when process reached a terminal state
            if (!in_array($instanceStatus, [
                WorkflowEngineService::INSTANCE_COMPLETED,
                WorkflowEngineService::INSTANCE_TERMINATED,
            ], true)) {
                return;
            }

            if ($aggregateType === 'sales_orders') {
                $this->handleSalesOrder($aggregateId, $approved, $instanceStatus);
                return;
            }

            if ($aggregateType === 'purchase_orders') {
                $this->handlePurchaseOrder($aggregateId, $approved, $instanceStatus);
                return;
            }
        } catch (Throwable $e) {
            Log::error('WorkflowTaskCompletedListener failed: ' . $e->getMessage(), [
                'payload' => $payload,
            ]);
            throw $e;
        }
    }

    private function handleSalesOrder(string $aggregateId, bool $approved, int $instanceStatus): void
    {
        if ($approved && $instanceStatus === WorkflowEngineService::INSTANCE_COMPLETED) {
            $this->salesOrderService->confirm($aggregateId);
            Log::info('Sales order confirmed via WorkflowTaskCompletedV1', [
                'sales_order_id' => $aggregateId,
            ]);
            return;
        }

        if (!$approved) {
            $this->salesOrderService->rejectFromWorkflow($aggregateId, cancel: false);
            Log::info('Sales order returned to draft after workflow rejection', [
                'sales_order_id' => $aggregateId,
            ]);
        }
    }

    private function handlePurchaseOrder(string $aggregateId, bool $approved, int $instanceStatus): void
    {
        if ($approved && $instanceStatus === WorkflowEngineService::INSTANCE_COMPLETED) {
            $this->purchaseOrderService->approveFromWorkflow($aggregateId);
            Log::info('Purchase order approved (SENT) via WorkflowTaskCompletedV1', [
                'purchase_order_id' => $aggregateId,
            ]);
            return;
        }

        if (!$approved) {
            $this->purchaseOrderService->rejectFromWorkflow($aggregateId, cancel: false);
            Log::info('Purchase order returned to draft after workflow rejection', [
                'purchase_order_id' => $aggregateId,
            ]);
        }
    }
}
