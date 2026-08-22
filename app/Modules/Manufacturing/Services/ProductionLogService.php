<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\ProductionLog;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Manufacturing\DTOs\ProductionLogDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionLogService
{
    public function createLog(ProductionLogDTO $dto): ProductionLog
    {
        return DB::transaction(function () use ($dto) {
            
            $log = ProductionLog::create([
                'production_order_id' => $dto->productionOrderId,
                'routing_id' => $dto->routingId,
                'log_type' => $dto->logType,
                'item_id' => $dto->itemId,
                'quantity_consumed' => $dto->quantityConsumed,
                'hours_spent' => $dto->hoursSpent,
                'logged_at' => $dto->loggedAt,
            ]);

            // اگر نوع لاگ مصرف کالا (1) یا ضایعات (3) است، به انبار اطلاع می‌دهیم
            if (in_array($dto->logType, [1, 3]) && $dto->itemId) {
                DB::table('event_outbox')->insert([
                    'event_id' => Str::uuid(),
                    'tenant_id' => $log->tenant_id ?? DB::raw('current_setting(\'app.current_tenant_id\')::uuid'),
                    'aggregate_type' => 'mfg_production_orders', 
                    'aggregate_id' => $dto->productionOrderId,
                    'event_type' => 'manufacturing.material.consumed',
                    'payload' => json_encode([
                        'log_id' => $log->production_log_id,
                        'order_id' => $dto->productionOrderId,
                        'item_id' => $dto->itemId,
                        'quantity' => $dto->quantityConsumed,
                        'type' => $dto->logType === 1 ? 'CONSUMPTION' : 'SCRAP',
                        'logged_at' => $dto->loggedAt
                    ]),
                    'status' => 1,
                    'retry_count' => 0,
                    'created_at' => now(),
                ]);
            }

            return $log;
        });
    }
}