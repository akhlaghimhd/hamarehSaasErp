<?php

namespace App\Modules\Manufacturing\DTOs;

use App\Modules\Manufacturing\Requests\StoreProductionLogRequest;

readonly class ProductionLogDTO
{
    public function __construct(
        public string $productionOrderId,
        public ?string $routingId,
        public int $logType,
        public ?string $itemId,
        public float $quantityConsumed,
        public float $hoursSpent,
        public string $loggedAt
    ) {}

    public static function fromRequest(StoreProductionLogRequest $request): self
    {
        return new self(
            productionOrderId: $request->validated('production_order_id'),
            routingId: $request->validated('routing_id'),
            logType: (int) $request->validated('log_type'),
            itemId: $request->validated('item_id'),
            quantityConsumed: (float) $request->validated('quantity_consumed', 0.0000),
            hoursSpent: (float) $request->validated('hours_spent', 0.0000),
            loggedAt: $request->validated('logged_at')
        );
    }
}