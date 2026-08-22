<?php

namespace App\Modules\Manufacturing\DTOs;

use App\Modules\Manufacturing\Requests\StoreProductionRoutingRequest;

readonly class ProductionRoutingDTO
{
    public function __construct(
        public string $productionOrderId,
        public string $workCenterId,
        public int $operationSequence,
        public string $operationName,
        public float $standardSetupTimeHours,
        public float $standardRunTimeHours,
        public int $status
    ) {}

    public static function fromRequest(StoreProductionRoutingRequest $request): self
    {
        return new self(
            productionOrderId: $request->validated('production_order_id'),
            workCenterId: $request->validated('work_center_id'),
            operationSequence: (int) $request->validated('operation_sequence'),
            operationName: $request->validated('operation_name'),
            standardSetupTimeHours: (float) $request->validated('standard_setup_time_hours', 0.0000),
            standardRunTimeHours: (float) $request->validated('standard_run_time_hours', 0.0000),
            status: (int) $request->validated('status', 1)
        );
    }
}