<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\DTOs\BomDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BomService
{
    /**
     * Create a new Bill of Materials (BOM) and dispatch an outbox event.
     */
    public function createBom(BomDTO $dto): Bom
    {
        return DB::transaction(function () use ($dto) {
            
            // 1. Create the BOM Header
            $bom = Bom::create([
                'item_id' => $dto->item_id, // Logical reference to MasterData Items (No physical FK)
                'bom_code' => $dto->bom_code,
                'bom_version' => $dto->bom_version ?? '1.0',
                'is_active' => $dto->is_active ?? true,
                'total_standard_cost' => $dto->total_standard_cost ?? 0.0000,
            ]);

            // 2. Insert BOM Items (If provided in DTO)
            if (!empty($dto->items)) {
                foreach ($dto->items as $itemDto) {
                    $bom->items()->create([
                        'raw_material_item_id' => $itemDto->raw_material_item_id, // Logical reference
                        'quantity' => $itemDto->quantity,
                        'unit_of_measure' => $itemDto->unit_of_measure,
                        'scrap_percentage' => $itemDto->scrap_percentage ?? 0.00,
                    ]);
                }
            }

            // 3. Insert into Event Outbox
            DB::table('event_outbox')->insert([
                'event_id' => Str::uuid(),
                'tenant_id' => $bom->tenant_id ?? app('tenant_id'),
                'aggregate_type' => 'mfg_boms',
                'aggregate_id' => $bom->bom_id,
                'event_type' => 'manufacturing.bom.created',
                'payload' => json_encode($bom->load('items')->toArray()),
                'status' => 1, // Pending
                'created_at' => now(),
            ]);

            return $bom;
        });
    }
}