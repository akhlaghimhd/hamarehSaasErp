<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class UpdatePartnerAgreementDTO
{
    public function __construct(
        public ?string $agreementNumber = null,
        public ?int $agreementType = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?int $paymentCycle = null,
        public ?string $description = null,
        public ?int $status = null,
        public array $raw = [],
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            agreementNumber: $validatedData['agreement_number'] ?? null,
            agreementType: array_key_exists('agreement_type', $validatedData)
                ? (int) $validatedData['agreement_type']
                : null,
            startDate: $validatedData['start_date'] ?? null,
            endDate: $validatedData['end_date'] ?? null,
            paymentCycle: array_key_exists('payment_cycle', $validatedData)
                ? (int) $validatedData['payment_cycle']
                : null,
            description: $validatedData['description'] ?? null,
            status: array_key_exists('status', $validatedData)
                ? (int) $validatedData['status']
                : null,
            raw: $validatedData,
        );
    }
}
