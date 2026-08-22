<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class CreateRoleDTO
{
    public function __construct(
        public string $roleName,
        public ?string $description,
        public array $permissionIds = []
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            roleName: $validatedData['role_name'],
            description: $validatedData['description'] ?? null,
            permissionIds: $validatedData['permission_ids'] ?? []
        );
    }
}