<?php

namespace App\Modules\ProcurementSales\Services\Concerns;

use App\Modules\ProcurementSales\Models\SalesOrder;
use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-WF-04 – Workflow approval helpers for Sales Orders.
 */
trait SalesOrderWorkflowSupport
{
    public function submitForApproval(
        string $id,
        string $definitionCode = 'SALES_ORDER_APPROVAL_V1'
    ): SalesOrder {
        try {
            return DB::transaction(function () use ($id, $definitionCode) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $order = SalesOrder::query()->lockForUpdate()->find($id);
                if (!$order) {
                    throw new NotFoundHttpException('Sales order not found.');
                }
                if ((int) $order->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft sales orders can be submitted for approval.');
                }
                if ($order->items()->count() === 0) {
                    throw new ConflictHttpException('Cannot submit a sales order with no lines.');
                }

                $engine = app(WorkflowEngineService::class);
                $engine->startInstance(
                    definitionCode: $definitionCode,
                    targetAggregateType: 'sales_orders',
                    targetAggregateId: $order->sales_order_id,
                    contextSnapshot: [
                        'order_number' => $order->order_number,
                        'total_amount' => (string) $order->total_amount,
                        'customer_id'  => $order->customer_id,
                        'warehouse_id' => $order->warehouse_id,
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
            Log::error('Failed to submit SalesOrder for approval: ' . $e->getMessage());
            throw $e;
        }
    }

    public function rejectFromWorkflow(string $id, bool $cancel = false): SalesOrder
    {
        $order = SalesOrder::query()->lockForUpdate()->find($id);
        if (!$order) {
            throw new NotFoundHttpException('Sales order not found.');
        }
        if ((int) $order->status !== self::STATUS_PENDING_APPROVAL) {
            throw new ConflictHttpException('Only pending-approval sales orders can be rejected from workflow.');
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
