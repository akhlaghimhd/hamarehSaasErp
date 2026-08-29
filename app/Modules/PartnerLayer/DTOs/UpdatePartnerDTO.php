<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class UpdatePartnerDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public int $partnerType = 1,
        public int $ownershipType = 1,
        public bool $commissionEnabled = true,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $address = null,
        public int $status = 1,
        public ?string $parentPartnerId = null,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            code: $validatedData['code'],
            name: $validatedData['name'],
            partnerType: (int) ($validatedData['partner_type'] ?? 1),
            ownershipType: (int) ($validatedData['ownership_type'] ?? 1),
            commissionEnabled: (bool) ($validatedData['commission_enabled'] ?? true),
            phone: $validatedData['phone'] ?? null,
            email: $validatedData['email'] ?? null,
            address: $validatedData['address'] ?? null,
            status: (int) ($validatedData['status'] ?? 1),
            parentPartnerId: $validatedData['parent_partner_id'] ?? null,
        );
    }
}
