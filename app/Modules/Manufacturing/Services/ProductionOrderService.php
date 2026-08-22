<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Manufacturing\DTOs\ProductionOrderDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionOrderService
{
    public function createProductionOrder(ProductionOrderDTO $dto): ProductionOrder
    {
        return DB::transaction(function () use ($dto) {
            
            // 1. ذخیره دستور تولید
            $productionOrder = ProductionOrder::create([
                'order_number' => $dto->orderNumber,
                'item_id' => $dto->itemId,
                'bom_id' => $dto->bomId,
                'planned_quantity' => $dto->plannedQuantity,
                'produced_quantity' => 0.0000, // مقدار تولید شده اولیه صفر است
                'start_date' => $dto->startDate,
                'end_date' => $dto->endDate,
                'status' => $dto->status,
                'row_version' => 1,
            ]);

            // 2. شلیک رویداد ایجاد دستور تولید به Outbox (تضمین یکپارچگی ناهمگام)
            // این رویداد می‌تواند باعث رزرو موجودی در انبار (ماژول ProcurementSales/Inventory) شود
            DB::table('event_outbox')->insert([
                'event_id' => Str::uuid(),
                'tenant_id' => $productionOrder->tenant_id ?? DB::raw('current_setting(\'app.current_tenant_id\')::uuid'),
                'aggregate_type' => 'mfg_production_orders',
                'aggregate_id' => $productionOrder->production_order_id,
                'event_type' => 'manufacturing.production_order.created',
                'payload' => json_encode($productionOrder->toArray()),
                'status' => 1, // Pending
                'retry_count' => 0,
                'created_at' => now(),
            ]);

            return $productionOrder;
        });
    }
}