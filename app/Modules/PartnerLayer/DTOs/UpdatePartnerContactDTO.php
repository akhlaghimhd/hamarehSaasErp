<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class UpdatePartnerContactDTO
{
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $roleTitle = null,
        public ?string $email = null,
        public ?string $phoneNumber = null,
        public ?bool $isPrimary = null,
        public array $raw = [],
    ) {}

    public static function fromRequest(array $d): self
    {
        return new self(
            firstName: $d['first_name'] ?? null,
            lastName: $d['last_name'] ?? null,
            roleTitle: $d['role_title'] ?? null,
            email: $d['email'] ?? null,
            phoneNumber: $d['phone_number'] ?? null,
            isPrimary: array_key_exists('is_primary', $d) ? (bool) $d['is_primary'] : null,
            raw: $d,
        );
    }
}
