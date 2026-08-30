<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class UpdatePartnerUserDTO
{
    public function __construct(
        public ?bool $isPrimary = null,
        public ?int $status = null,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            isPrimary: array_key_exists('is_primary', $validatedData)
                ? (bool) $validatedData['is_primary']
                : null,
            status: array_key_exists('status', $validatedData)
                ? (int) $validatedData['status']
                : null,
        );
    }
}
