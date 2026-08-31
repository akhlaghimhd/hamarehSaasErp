<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class UpdatePartnerPayoutDTO
{
    public function __construct(
        public ?string $payoutNumber = null,
        public ?string $totalAmount = null,
        public ?string $currencyId = null,
        public ?string $bankAccountId = null,
        public ?string $payoutDate = null,
        public ?string $paymentReference = null,
        public ?int $status = null,
        public ?string $description = null,
        public array $raw = [],
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            payoutNumber: $validatedData['payout_number'] ?? null,
            totalAmount: array_key_exists('total_amount', $validatedData)
                ? (string) $validatedData['total_amount']
                : null,
            currencyId: $validatedData['currency_id'] ?? null,
            bankAccountId: $validatedData['bank_account_id'] ?? null,
            payoutDate: $validatedData['payout_date'] ?? null,
            paymentReference: $validatedData['payment_reference'] ?? null,
            status: array_key_exists('status', $validatedData)
                ? (int) $validatedData['status']
                : null,
            description: $validatedData['description'] ?? null,
            raw: $validatedData,
        );
    }
}
