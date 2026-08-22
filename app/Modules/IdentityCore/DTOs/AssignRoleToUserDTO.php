<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class AssignRoleToUserDTO
{
    public function __construct(
        public string $userId,
        public array $roleIds
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            userId: $validatedData['user_id'],
            roleIds: $validatedData['role_ids']
        );
    }
}