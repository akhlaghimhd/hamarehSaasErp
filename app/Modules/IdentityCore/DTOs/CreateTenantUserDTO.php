<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class CreateTenantUserDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $firstName,
        public string $lastName,
        public ?string $mobile = null,
        public bool $isOwner = false,
        public array $roleIds = [],
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            email: $validatedData['email'],
            password: $validatedData['password'],
            firstName: $validatedData['first_name'],
            lastName: $validatedData['last_name'],
            mobile: $validatedData['mobile'] ?? null,
            isOwner: (bool) ($validatedData['is_owner'] ?? false),
            roleIds: $validatedData['role_ids'] ?? [],
        );
    }
}