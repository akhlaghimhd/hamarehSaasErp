<?php

namespace App\Modules\Accounting\DTOs;

class FiscalPeriodDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly bool $isClosed = false
    ) {}
}