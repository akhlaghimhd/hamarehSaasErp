<?php

namespace App\Modules\Accounting\DTOs;

class FinancialVoucherItemDTO
{
    public function __construct(
        public readonly string $voucherId,
        public readonly string $accountId, // شناسه حساب معین/تفصیلی
        public readonly ?string $costCenterId, // ارجاع منطقی به MasterData
        public readonly ?string $businessPartnerId, // ارجاع منطقی به MasterData
        public readonly string $description,
        public readonly float $debit,
        public readonly float $credit
    ) {}
}