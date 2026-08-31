<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class UpdatePartnerCommissionDTO
{
    public function __construct(
        public ?int $status = null,
        public ?string $paidAt = null,
        public array $raw = [],
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            status: array_key_exists('status', $validatedData)
                ? (int) $validatedData['status']
                : null,
            paidAt: $validatedData['paid_at'] ?? null,
            raw: $validatedData,
        );
    }
}
