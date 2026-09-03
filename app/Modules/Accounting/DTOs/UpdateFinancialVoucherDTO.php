<?php

namespace App\Modules\Accounting\DTOs;

use App\Modules\Accounting\Requests\UpdateFinancialVoucherRequest;

readonly class UpdateFinancialVoucherDTO
{
    public function __construct(
        public ?string $voucherDate = null,
        public ?string $description = null,
        public ?float $totalAmount = null,
        public ?string $referenceNumber = null,
        public ?string $currencyId = null
    ) {}

    public static function fromRequest(UpdateFinancialVoucherRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            voucherDate: $validated['voucher_date'] ?? null,
            description: $validated['description'] ?? null,
            totalAmount: isset($validated['total_amount']) ? (float) $validated['total_amount'] : null,
            referenceNumber: $validated['reference_number'] ?? null,
            currencyId: $validated['currency_id'] ?? null
        );
    }
}
