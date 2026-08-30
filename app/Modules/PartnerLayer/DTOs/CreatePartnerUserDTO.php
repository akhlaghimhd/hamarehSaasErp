<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class CreatePartnerUserDTO
{
    public function __construct(
        public string $partnerId,
        public string $userId,
        public bool $isPrimary = false,
        public int $status = 1,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            partnerId: $validatedData['partner_id'],
            userId: $validatedData['user_id'],
            isPrimary: (bool) ($validatedData['is_primary'] ?? false),
            status: (int) ($validatedData['status'] ?? 1),
        );
    }
}
