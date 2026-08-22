<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\DTOs\ResourceAllocation\AllocateResourceDTO;
use App\Modules\ProjectManagement\Models\ResourceAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResourceAllocationService
{
    public function allocate(AllocateResourceDTO $dto): ResourceAllocation
    {
        return DB::transaction(function () use ($dto) {
            $allocation = ResourceAllocation::create([
                'task_id'            => $dto->task_id,
                'resource_type'      => $dto->resource_type,
                'resource_id'        => $dto->resource_id,
                'allocated_quantity' => $dto->allocated_quantity,
                'start_date'         => $dto->start_date,
                'end_date'           => $dto->end_date
            ]);

            $this->publishEventToOutbox($allocation);

            return $allocation;
        });
    }

    private function publishEventToOutbox(ResourceAllocation $allocation): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $allocation->tenant_id,
            'aggregate_type' => 'resource_allocations',
            'aggregate_id'   => $allocation->allocation_id,
            'event_type'     => 'project_management.resource.allocated',
            'payload'        => json_encode([
                'allocation_id'      => $allocation->allocation_id,
                'task_id'            => $allocation->task_id,
                'resource_type'      => $allocation->resource_type,
                'resource_id'        => $allocation->resource_id,
                'allocated_quantity' => $allocation->allocated_quantity
            ]),
            'status'         => 1, // Pending
            'retry_count'    => 0,
            'created_at'     => now(),
        ]);
    }
}