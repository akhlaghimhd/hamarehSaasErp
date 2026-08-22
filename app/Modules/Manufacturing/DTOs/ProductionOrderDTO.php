<?php

namespace App\Modules\Manufacturing\DTOs;

use App\Modules\Manufacturing\Requests\StoreProductionOrderRequest;

readonly class ProductionOrderDTO
{
    public function __construct(
        public string $orderNumber,
        public string $itemId,
        public string $bomId,
        public float $plannedQuantity,
        public string $startDate,
        public ?string $endDate,
        public int $status
    ) {}

    public static function fromRequest(StoreProductionOrderRequest $request): self
    {
        return new self(
            orderNumber: $request->validated('order_number'),
            itemId: $request->validated('item_id'),
            bomId: $request->validated('bom_id'),
            plannedQuantity: (float) $request->validated('planned_quantity'),
            startDate: $request->validated('start_date'),
            endDate: $request->validated('end_date'),
            status: (int) $request->validated('status', 1) // پیش‌فرض Draft
        );
    }
}