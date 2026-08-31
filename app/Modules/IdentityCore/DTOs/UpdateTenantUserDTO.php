<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class UpdateTenantUserDTO
{
    public function __construct(
        public string $tenantUserId,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $mobile = null,
        public ?bool $isOwner = null,
        public ?int $status = null,
    ) {}

    public static function fromRequest(string $tenantUserId, array $validatedData): self
    {
        return new self(
            tenantUserId: $tenantUserId,
            firstName: $validatedData['first_name'] ?? null,
            lastName: $validatedData['last_name'] ?? null,
            mobile: $validatedData['mobile'] ?? null,
            isOwner: array_key_exists('is_owner', $validatedData)
                ? (bool) $validatedData['is_owner']
                : null,
            status: array_key_exists('status', $validatedData)
                ? (int) $validatedData['status']
                : null,
        );
    }
}
