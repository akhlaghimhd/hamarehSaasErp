<?php

namespace App\Modules\ProjectManagement\DTOs\ResourceAllocation;

use Illuminate\Http\Request;

readonly class AllocateResourceDTO
{
    public function __construct(
        public string $task_id,
        public int $resource_type,
        public string $resource_id,
        public float $allocated_quantity,
        public string $start_date,
        public string $end_date
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            task_id: $request->validated('task_id'),
            resource_type: (int) $request->validated('resource_type'),
            resource_id: $request->validated('resource_id'),
            allocated_quantity: (float) $request->validated('allocated_quantity', 1.0000),
            start_date: $request->validated('start_date'),
            end_date: $request->validated('end_date')
        );
    }
}