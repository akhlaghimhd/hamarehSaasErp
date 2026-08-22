<?php

namespace App\Modules\Accounting\DTOs;

class FinancialVoucherDTO
{
    public function __construct(
        public readonly string $voucherDate,
        public readonly string $description,
        public readonly float $totalAmount,
        public readonly string $referenceNumber,
        public readonly ?string $currencyId = null
    ) {}
}