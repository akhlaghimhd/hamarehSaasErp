<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class CreateTenantUserDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $mobile,
        public ?string $emailLocalPart = null,
        public bool $isOwner = false,
        public array $roleIds = [],
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            firstName: $validatedData['first_name'],
            lastName: $validatedData['last_name'],
            mobile: $validatedData['mobile'],
            emailLocalPart: $validatedData['email_local_part'] ?? null,
            isOwner: (bool) ($validatedData['is_owner'] ?? false),
            roleIds: $validatedData['role_ids'] ?? [],
        );
    }
}
