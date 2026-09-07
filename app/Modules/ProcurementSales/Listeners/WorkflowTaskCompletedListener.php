<?php

namespace App\Modules\ProcurementSales\Listeners;

use App\Modules\ProcurementSales\Services\SalesOrderService;
use App\Modules\Workflow\Events\WorkflowTaskCompletedV1;
use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * L6-WF-04 – When workflow completes for a sales order, confirm (approve) or reject.
 * Consumes workflow.task.completed.v1 (via Event::listen or Outbox processor).
 */
class WorkflowTaskCompletedListener
{
    public function __construct(
        private readonly SalesOrderService $salesOrderService,
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

            if ($aggregateType !== 'sales_orders' || empty($aggregateId)) {
                return;
            }

            // Only act when process reached a terminal state
            if (!in_array($instanceStatus, [
                WorkflowEngineService::INSTANCE_COMPLETED,
                WorkflowEngineService::INSTANCE_TERMINATED,
            ], true)) {
                return;
            }

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
        } catch (Throwable $e) {
            Log::error('WorkflowTaskCompletedListener failed: ' . $e->getMessage(), [
                'payload' => $payload,
            ]);
            throw $e;
        }
    }
}
