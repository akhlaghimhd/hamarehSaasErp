<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\WorkCenter;
use App\Modules\Manufacturing\DTOs\WorkCenterDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkCenterService
{
    /**
     * Create a new Work Center and dispatch an outbox event synchronously in DB transaction.
     */
    public function createWorkCenter(WorkCenterDTO $dto): WorkCenter
    {
        return DB::transaction(function () use ($dto) {
            
            // 1. Create the business entity (tenant_id handled by TenantScoped)
            $workCenter = WorkCenter::create([
                'code' => $dto->code,
                'name' => $dto->name,
                'capacity_hours_per_day' => $dto->capacity_hours_per_day,
                'efficiency_percentage' => $dto->efficiency_percentage,
                'cost_per_hour' => $dto->cost_per_hour,
                'status' => $dto->status,
                'created_by' => $dto->created_by,
            ]);

            // 2. Insert into Event Outbox (Rule: Asynchronous module communication)
            DB::table('event_outbox')->insert([
                'event_id' => Str::uuid(),
                'tenant_id' => $workCenter->tenant_id ?? app('tenant_id'), // Fetch from context if needed
                'aggregate_type' => 'mfg_work_centers',
                'aggregate_id' => $workCenter->work_center_id,
                'event_type' => 'manufacturing.work_center.created',
                'payload' => json_encode($workCenter->toArray()),
                'status' => 1, // 1: Pending
                'created_at' => now(),
            ]);

            return $workCenter;
        });
    }
}