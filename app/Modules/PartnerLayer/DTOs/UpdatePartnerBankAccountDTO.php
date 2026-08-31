<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class UpdatePartnerBankAccountDTO
{
    public function __construct(
        public ?string $bankName = null,
        public ?string $accountNumber = null,
        public ?string $shabaNumber = null,
        public ?string $cardNumber = null,
        public ?bool $isActive = null,
        public array $raw = [],
    ) {}

    public static function fromRequest(array $d): self
    {
        return new self(
            bankName: $d['bank_name'] ?? null,
            accountNumber: $d['account_number'] ?? null,
            shabaNumber: $d['shaba_number'] ?? null,
            cardNumber: $d['card_number'] ?? null,
            isActive: array_key_exists('is_active', $d) ? (bool) $d['is_active'] : null,
            raw: $d,
        );
    }
}
