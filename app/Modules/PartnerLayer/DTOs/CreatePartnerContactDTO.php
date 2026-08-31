<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class CreatePartnerContactDTO
{
    public function __construct(
        public string $partnerId,
        public string $firstName,
        public string $lastName,
        public ?string $roleTitle = null,
        public ?string $email = null,
        public ?string $phoneNumber = null,
        public bool $isPrimary = false,
    ) {}

    public static function fromRequest(array $d): self
    {
        return new self(
            partnerId: $d['partner_id'],
            firstName: $d['first_name'],
            lastName: $d['last_name'],
            roleTitle: $d['role_title'] ?? null,
            email: $d['email'] ?? null,
            phoneNumber: $d['phone_number'] ?? null,
            isPrimary: (bool) ($d['is_primary'] ?? false),
        );
    }
}
