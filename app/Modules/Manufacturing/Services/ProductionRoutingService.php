<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Manufacturing\Models\ProductionRouting;
use App\Modules\Manufacturing\Models\WorkCenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-MFG-03 — Shop-floor routing steps on a production order.
 * status: 1 Pending, 2 Active, 3 Completed
 */
class ProductionRoutingService
{
    public const STATUS_PENDING = 1;
    public const STATUS_ACTIVE = 2;
    public const STATUS_COMPLETED = 3;

    public function listForOrder(string $productionOrderId): Collection
    {
        return ProductionRouting::query()
            ->where('production_order_id', $productionOrderId)
            ->orderBy('operation_sequence')
            ->get();
    }

    /**
     * @param  array{work_center_id:string,operation_sequence:int,operation_name:string,standard_setup_time_hours?:float,standard_run_time_hours?:float}  $data
     */
    public function addStep(string $productionOrderId, array $data): ProductionRouting
    {
        try {
            return DB::transaction(function () use ($productionOrderId, $data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $order = ProductionOrder::query()->find($productionOrderId);
                if (!$order) {
                    throw new NotFoundHttpException('Production order not found.');
                }
                if ((int) $order->status === ProductionOrderService::STATUS_COMPLETED) {
                    throw new ConflictHttpException('Cannot add routing steps to a completed order.');
                }
                if ((int) $order->status === ProductionOrderService::STATUS_CANCELLED) {
                    throw new ConflictHttpException('Cannot add routing steps to a cancelled order.');
                }

                $wc = WorkCenter::query()->find($data['work_center_id']);
                if (!$wc) {
                    throw new NotFoundHttpException('Work center not found.');
                }

                return ProductionRouting::create([
                    'tenant_id'                 => $tenantId,
                    'production_order_id'       => $productionOrderId,
                    'work_center_id'            => $data['work_center_id'],
                    'operation_sequence'        => (int) $data['operation_sequence'],
                    'operation_name'            => $data['operation_name'],
                    'standard_setup_time_hours' => $data['standard_setup_time_hours'] ?? 0,
                    'standard_run_time_hours'   => $data['standard_run_time_hours'] ?? 0,
                    'status'                    => self::STATUS_PENDING,
                    'created_by'                => $userId,
                    'row_version'               => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to add routing step: ' . $e->getMessage());
            throw $e;
        }
    }

    public function start(string $routingId): ProductionRouting
    {
        $step = ProductionRouting::query()->lockForUpdate()->find($routingId);
        if (!$step) {
            throw new NotFoundHttpException('Routing step not found.');
        }
        if ((int) $step->status !== self::STATUS_PENDING) {
            throw new ConflictHttpException('Only pending routing steps can be started.');
        }

        $step->update([
            'status'      => self::STATUS_ACTIVE,
            'updated_by'  => Context::get('user_id'),
            'row_version' => ((int) ($step->row_version ?? 1)) + 1,
        ]);

        $order = ProductionOrder::query()->find($step->production_order_id);
        if ($order && (int) $order->status === ProductionOrderService::STATUS_RELEASED) {
            $order->update([
                'status'      => ProductionOrderService::STATUS_IN_PROGRESS,
                'updated_by'  => Context::get('user_id'),
                'row_version' => ((int) ($order->row_version ?? 1)) + 1,
            ]);
        }

        return $step->fresh();
    }

    public function complete(string $routingId): ProductionRouting
    {
        $step = ProductionRouting::query()->lockForUpdate()->find($routingId);
        if (!$step) {
            throw new NotFoundHttpException('Routing step not found.');
        }
        if ((int) $step->status !== self::STATUS_ACTIVE) {
            throw new ConflictHttpException('Only active routing steps can be completed.');
        }

        $step->update([
            'status'      => self::STATUS_COMPLETED,
            'updated_by'  => Context::get('user_id'),
            'row_version' => ((int) ($step->row_version ?? 1)) + 1,
        ]);

        return $step->fresh();
    }
}
