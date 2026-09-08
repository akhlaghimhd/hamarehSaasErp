<?php

namespace App\Modules\ProcurementSales\Listeners;

use App\Modules\ProcurementSales\Services\PurchaseOrderService;
use App\Modules\ProcurementSales\Services\SalesInvoiceService;
use App\Modules\ProcurementSales\Services\SalesOrderService;
use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * When workflow completes for Sales/Purchase orders/invoices, apply domain transition.
 */
class WorkflowTaskCompletedListener
{
    public function __construct(
        private readonly SalesOrderService $salesOrderService,
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly SalesInvoiceService $salesInvoiceService,
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

            if ($aggregateType === 'sales_invoices') {
                $this->handleSalesInvoice($aggregateId, $approved, $instanceStatus);
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
            Log::info('Sales order confirmed from workflow', ['sales_order_id' => $aggregateId]);
            return;
        }
        if (!$approved || $instanceStatus === WorkflowEngineService::INSTANCE_TERMINATED) {
            $this->salesOrderService->rejectFromWorkflow($aggregateId);
            Log::info('Sales order rejected from workflow', ['sales_order_id' => $aggregateId]);
        }
    }

    private function handlePurchaseOrder(string $aggregateId, bool $approved, int $instanceStatus): void
    {
        if ($approved && $instanceStatus === WorkflowEngineService::INSTANCE_COMPLETED) {
            $this->purchaseOrderService->approveFromWorkflow($aggregateId);
            Log::info('Purchase order approved from workflow', ['purchase_order_id' => $aggregateId]);
            return;
        }
        if (!$approved || $instanceStatus === WorkflowEngineService::INSTANCE_TERMINATED) {
            $this->purchaseOrderService->rejectFromWorkflow($aggregateId);
            Log::info('Purchase order rejected from workflow', ['purchase_order_id' => $aggregateId]);
        }
    }

    private function handleSalesInvoice(string $aggregateId, bool $approved, int $instanceStatus): void
    {
        if ($approved && $instanceStatus === WorkflowEngineService::INSTANCE_COMPLETED) {
            $this->salesInvoiceService->approveFromWorkflow($aggregateId);
            Log::info('Sales invoice approved from workflow', ['sales_invoice_id' => $aggregateId]);
            return;
        }
        if (!$approved || $instanceStatus === WorkflowEngineService::INSTANCE_TERMINATED) {
            $this->salesInvoiceService->rejectFromWorkflow($aggregateId);
            Log::info('Sales invoice rejected from workflow', ['sales_invoice_id' => $aggregateId]);
        }
    }
}
