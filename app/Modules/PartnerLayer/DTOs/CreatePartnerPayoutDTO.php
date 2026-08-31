<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class CreatePartnerPayoutDTO
{
    public function __construct(
        public string $partnerId,
        public string $payoutNumber,
        public string $totalAmount,
        public string $currencyId,
        public ?string $bankAccountId = null,
        public ?string $payoutDate = null,
        public ?string $paymentReference = null,
        public int $status = 1,
        public ?string $description = null,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            partnerId: $validatedData['partner_id'],
            payoutNumber: $validatedData['payout_number'],
            totalAmount: (string) $validatedData['total_amount'],
            currencyId: $validatedData['currency_id'],
            bankAccountId: $validatedData['bank_account_id'] ?? null,
            payoutDate: $validatedData['payout_date'] ?? null,
            paymentReference: $validatedData['payment_reference'] ?? null,
            status: (int) ($validatedData['status'] ?? 1),
            description: $validatedData['description'] ?? null,
        );
    }
}
