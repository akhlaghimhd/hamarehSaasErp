<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\ProductionRouting;
use App\Modules\Manufacturing\DTOs\ProductionRoutingDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionRoutingService
{
    public function createRouting(ProductionRoutingDTO $dto): ProductionRouting
    {
        return DB::transaction(function () use ($dto) {
            
            $routing = ProductionRouting::create([
                'production_order_id' => $dto->productionOrderId,
                'work_center_id' => $dto->workCenterId,
                'operation_sequence' => $dto->operationSequence,
                'operation_name' => $dto->operationName,
                'standard_setup_time_hours' => $dto->standardSetupTimeHours,
                'standard_run_time_hours' => $dto->standardRunTimeHours,
                'status' => $dto->status,
                'row_version' => 1,
            ]);

            // ثبت رویداد تنظیم مسیردهی برای موتور گردش کار (Workflow) یا سایر سیستم‌ها
            DB::table('event_outbox')->insert([
                'event_id' => Str::uuid(),
                'tenant_id' => $routing->tenant_id ?? DB::raw('current_setting(\'app.current_tenant_id\')::uuid'),
                'aggregate_type' => 'mfg_production_orders', 
                'aggregate_id' => $dto->productionOrderId,
                'event_type' => 'manufacturing.routing.created',
                'payload' => json_encode($routing->toArray()),
                'status' => 1,
                'retry_count' => 0,
                'created_at' => now(),
            ]);

            return $routing;
        });
    }
}