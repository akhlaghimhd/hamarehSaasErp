<?php

namespace App\Modules\Accounting\DTOs;

use App\Modules\Accounting\Requests\UpdateFinancialVoucherItemRequest;

readonly class UpdateFinancialVoucherItemDTO
{
    public function __construct(
        public ?string $accountId = null,
        public ?string $costCenterId = null,
        public ?string $businessPartnerId = null,
        public ?string $description = null,
        public ?float $debit = null,
        public ?float $credit = null
    ) {}

    public static function fromRequest(UpdateFinancialVoucherItemRequest $request): self
    {
        $v = $request->validated();

        return new self(
            accountId: $v['account_id'] ?? null,
            costCenterId: $v['cost_center_id'] ?? null,
            businessPartnerId: $v['business_partner_id'] ?? null,
            description: $v['description'] ?? null,
            debit: isset($v['debit']) ? (float) $v['debit'] : null,
            credit: isset($v['credit']) ? (float) $v['credit'] : null
        );
    }
}
