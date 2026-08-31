<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class CreatePartnerBankAccountDTO
{
    public function __construct(
        public string $partnerId,
        public string $bankName,
        public string $shabaNumber,
        public ?string $accountNumber = null,
        public ?string $cardNumber = null,
        public bool $isActive = true,
    ) {}

    public static function fromRequest(array $d): self
    {
        return new self(
            partnerId: $d['partner_id'],
            bankName: $d['bank_name'],
            shabaNumber: $d['shaba_number'],
            accountNumber: $d['account_number'] ?? null,
            cardNumber: $d['card_number'] ?? null,
            isActive: (bool) ($d['is_active'] ?? true),
        );
    }
}
