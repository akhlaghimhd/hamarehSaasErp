<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\BomItem;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\DTOs\BomItemDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BomItemService
{
    public function addBomItem(BomItemDTO $dto): BomItem
    {
        return DB::transaction(function () use ($dto) {
            
            // 1. ذخیره در دیتابیس
            $bomItem = BomItem::create([
                'bom_id' => $dto->bomId,
                'item_id' => $dto->itemId,
                'quantity' => $dto->quantity,
                'scrap_percentage' => $dto->scrapPercentage,
                'row_version' => 1,
            ]);

            // 2. آپدیت کردن version یا row_version در سربرگ BOM (اختیاری اما توصیه شده برای حفظ یکپارچگی)
            Bom::where('bom_id', $dto->bomId)->increment('row_version');

            // 3. شلیک رویداد تغییر فرمولاسیون به Outbox
            DB::table('event_outbox')->insert([
                'event_id' => Str::uuid(),
                'tenant_id' => $bomItem->tenant_id ?? DB::raw('current_setting(\'app.current_tenant_id\')::uuid'),
                'aggregate_type' => 'mfg_boms', // Aggregate Root is the BOM Header
                'aggregate_id' => $bomItem->bom_id,
                'event_type' => 'manufacturing.bom_item.added',
                'payload' => json_encode($bomItem->toArray()),
                'status' => 1, // Pending
                'retry_count' => 0,
                'created_at' => now(),
            ]);

            return $bomItem;
        });
    }
}