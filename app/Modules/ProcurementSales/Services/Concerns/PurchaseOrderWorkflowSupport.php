<?php

namespace App\Modules\ProcurementSales\Services\Concerns;

use App\Modules\ProcurementSales\Models\PurchaseOrder;
use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-WF-04 extension – Workflow approval helpers for Purchase Orders.
 * Mirror of SalesOrderWorkflowSupport: Draft → Pending Approval → Sent (approve) / Draft (reject).
 */
trait PurchaseOrderWorkflowSupport
{
    public function submitForApproval(
        string $id,
        string $definitionCode = 'PURCHASE_ORDER_APPROVAL_V1'
    ): PurchaseOrder {
        try {
            return DB::transaction(function () use ($id, $definitionCode) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $order = PurchaseOrder::query()->lockForUpdate()->find($id);
                if (!$order) {
                    throw new NotFoundHttpException('Purchase order not found.');
                }
                if ((int) $order->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft purchase orders can be submitted for approval.');
                }
                if ($order->items()->count() === 0) {
                    throw new ConflictHttpException('Cannot submit a purchase order with no lines.');
                }

                $engine = app(WorkflowEngineService::class);
                $engine->startInstance(
                    definitionCode: $definitionCode,
                    targetAggregateType: 'purchase_orders',
                    targetAggregateId: $order->purchase_order_id,
                    contextSnapshot: [
                        'order_number' => $order->order_number,
                        'total_amount' => (string) $order->total_amount,
                        'supplier_id'  => $order->supplier_id,
                    ],
                );

                $order->update([
                    'status'      => self::STATUS_PENDING_APPROVAL,
                    'updated_by'  => $userId,
                    'row_version' => ((int) ($order->row_version ?? 1)) + 1,
                ]);

                return $order->fresh(['items']);
            });
        } catch (Exception $e) {
            Log::error('Failed to submit PurchaseOrder for approval: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Workflow approve → STATUS_SENT (PO authorized to be sent to supplier).
     */
    public function approveFromWorkflow(string $id): PurchaseOrder
    {
        $order = PurchaseOrder::query()->lockForUpdate()->find($id);
        if (!$order) {
            throw new NotFoundHttpException('Purchase order not found.');
        }
        if ((int) $order->status !== self::STATUS_PENDING_APPROVAL) {
            throw new ConflictHttpException('Only pending-approval purchase orders can be approved from workflow.');
        }

        $userId = Context::get('user_id');
        $order->update([
            'status'      => self::STATUS_SENT,
            'updated_by'  => $userId,
            'row_version' => ((int) ($order->row_version ?? 1)) + 1,
        ]);

        return $order->fresh(['items']);
    }

    public function rejectFromWorkflow(string $id, bool $cancel = false): PurchaseOrder
    {
        $order = PurchaseOrder::query()->lockForUpdate()->find($id);
        if (!$order) {
            throw new NotFoundHttpException('Purchase order not found.');
        }
        if ((int) $order->status !== self::STATUS_PENDING_APPROVAL) {
            throw new ConflictHttpException('Only pending-approval purchase orders can be rejected from workflow.');
        }

        $userId = Context::get('user_id');
        $order->update([
            'status'      => $cancel ? self::STATUS_CANCELLED : self::STATUS_DRAFT,
            'updated_by'  => $userId,
            'row_version' => ((int) ($order->row_version ?? 1)) + 1,
        ]);

        return $order->fresh(['items']);
    }
}
