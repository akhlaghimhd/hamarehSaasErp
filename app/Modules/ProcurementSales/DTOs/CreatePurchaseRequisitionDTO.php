<?php

namespace App\Modules\ProcurementSales\DTOs;

readonly class CreatePurchaseRequisitionDTO
{
    /** @param list<PurchaseRequisitionItemDTO> $items */
    public function __construct(
        public string $departmentId,
        public string $requiredDate,
        public int $priority = 2,
        public ?string $description = null,
        public array $items = [],
    ) {}
}
