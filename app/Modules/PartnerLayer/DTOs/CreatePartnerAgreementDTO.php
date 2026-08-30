<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class CreatePartnerAgreementDTO
{
    public function __construct(
        public string $partnerId,
        public string $agreementNumber,
        public int $agreementType = 1,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?int $paymentCycle = null,
        public ?string $description = null,
        public int $status = 1,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            partnerId: $validatedData['partner_id'],
            agreementNumber: $validatedData['agreement_number'],
            agreementType: (int) ($validatedData['agreement_type'] ?? 1),
            startDate: $validatedData['start_date'] ?? null,
            endDate: $validatedData['end_date'] ?? null,
            paymentCycle: isset($validatedData['payment_cycle'])
                ? (int) $validatedData['payment_cycle']
                : null,
            description: $validatedData['description'] ?? null,
            status: (int) ($validatedData['status'] ?? 1),
        );
    }
}
