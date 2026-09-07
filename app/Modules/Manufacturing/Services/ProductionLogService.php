<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\ProductionLog;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Manufacturing\Models\ProductionRouting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-MFG-03 — Shop-floor logs.
 * log_type: 1 Material, 2 Labor/Machine time, 3 Scrap
 */
class ProductionLogService
{
    public const TYPE_MATERIAL = 1;
    public const TYPE_LABOR = 2;
    public const TYPE_SCRAP = 3;

    public function listForOrder(string $productionOrderId): Collection
    {
        return ProductionLog::query()
            ->where('production_order_id', $productionOrderId)
            ->orderByDesc('logged_at')
            ->get();
    }

    /**
     * @param  array{log_type:int,routing_id?:?string,item_id?:?string,quantity_consumed?:float,hours_spent?:float}  $data
     */
    public function log(string $productionOrderId, array $data): ProductionLog
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
                if (in_array((int) $order->status, [
                    ProductionOrderService::STATUS_DRAFT,
                    ProductionOrderService::STATUS_CANCELLED,
                ], true)) {
                    throw new ConflictHttpException('Cannot log against draft or cancelled production orders.');
                }

                $logType = (int) $data['log_type'];
                if (!in_array($logType, [self::TYPE_MATERIAL, self::TYPE_LABOR, self::TYPE_SCRAP], true)) {
                    throw new ConflictHttpException('Invalid log_type.');
                }

                $routingId = $data['routing_id'] ?? null;
                if ($routingId) {
                    $step = ProductionRouting::query()
                        ->where('routing_id', $routingId)
                        ->where('production_order_id', $productionOrderId)
                        ->first();
                    if (!$step) {
                        throw new NotFoundHttpException('Routing step not found for this production order.');
                    }
                }

                return ProductionLog::create([
                    'tenant_id'           => $tenantId,
                    'production_order_id' => $productionOrderId,
                    'routing_id'          => $routingId,
                    'log_type'            => $logType,
                    'item_id'             => $data['item_id'] ?? null,
                    'quantity_consumed'   => $data['quantity_consumed'] ?? 0,
                    'hours_spent'         => $data['hours_spent'] ?? 0,
                    'logged_at'           => now(),
                    'created_by'          => $userId,
                    'row_version'         => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to write production log: ' . $e->getMessage());
            throw $e;
        }
    }
}
