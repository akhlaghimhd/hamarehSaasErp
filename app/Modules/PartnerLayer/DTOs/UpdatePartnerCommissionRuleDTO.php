<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class UpdatePartnerCommissionRuleDTO
{
    public function __construct(
        public ?int $revenueType = null,
        public ?int $commissionType = null,
        public ?string $commissionValue = null,
        public ?int $calculationBasis = null,
        public ?string $minimumAmount = null,
        public ?string $maximumAmount = null,
        public ?string $effectiveFrom = null,
        public ?string $effectiveTo = null,
        public ?int $status = null,
        public array $raw = [],
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            revenueType: array_key_exists('revenue_type', $validatedData)
                ? (int) $validatedData['revenue_type']
                : null,
            commissionType: array_key_exists('commission_type', $validatedData)
                ? (int) $validatedData['commission_type']
                : null,
            commissionValue: array_key_exists('commission_value', $validatedData)
                ? (string) $validatedData['commission_value']
                : null,
            calculationBasis: array_key_exists('calculation_basis', $validatedData)
                ? (int) $validatedData['calculation_basis']
                : null,
            minimumAmount: array_key_exists('minimum_amount', $validatedData)
                ? ($validatedData['minimum_amount'] !== null
                    ? (string) $validatedData['minimum_amount']
                    : null)
                : null,
            maximumAmount: array_key_exists('maximum_amount', $validatedData)
                ? ($validatedData['maximum_amount'] !== null
                    ? (string) $validatedData['maximum_amount']
                    : null)
                : null,
            effectiveFrom: $validatedData['effective_from'] ?? null,
            effectiveTo: $validatedData['effective_to'] ?? null,
            status: array_key_exists('status', $validatedData)
                ? (int) $validatedData['status']
                : null,
            raw: $validatedData,
        );
    }
}
