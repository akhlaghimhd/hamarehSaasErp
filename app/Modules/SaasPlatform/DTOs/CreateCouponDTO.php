<?php

namespace App\Modules\SaasPlatform\DTOs;

readonly class CreateCouponDTO
{
    public function __construct(
        public string $code,
        public int $discountType,
        public float $discountValue,
        public ?string $startDate = null,
        public ?string $endDate = null
    ) {
    }

    public static function fromRequest(array $validated): self
    {
        return new self(
            code: $validated['code'],
            discountType: (int) $validated['discount_type'],
            discountValue: (float) $validated['discount_value'],
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null
        );
    }
}