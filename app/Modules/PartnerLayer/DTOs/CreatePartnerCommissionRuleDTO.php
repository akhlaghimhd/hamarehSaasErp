<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class CreatePartnerCommissionRuleDTO
{
    public function __construct(
        public string $agreementId,
        public int $revenueType,
        public int $commissionType,
        public string $commissionValue,
        public int $calculationBasis = 1,
        public ?string $minimumAmount = null,
        public ?string $maximumAmount = null,
        public ?string $effectiveFrom = null,
        public ?string $effectiveTo = null,
        public int $status = 1,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            agreementId: $validatedData['agreement_id'],
            revenueType: (int) $validatedData['revenue_type'],
            commissionType: (int) $validatedData['commission_type'],
            commissionValue: (string) $validatedData['commission_value'],
            calculationBasis: (int) ($validatedData['calculation_basis'] ?? 1),
            minimumAmount: isset($validatedData['minimum_amount'])
                ? (string) $validatedData['minimum_amount']
                : null,
            maximumAmount: isset($validatedData['maximum_amount'])
                ? (string) $validatedData['maximum_amount']
                : null,
            effectiveFrom: $validatedData['effective_from'] ?? null,
            effectiveTo: $validatedData['effective_to'] ?? null,
            status: (int) ($validatedData['status'] ?? 1),
        );
    }
}
