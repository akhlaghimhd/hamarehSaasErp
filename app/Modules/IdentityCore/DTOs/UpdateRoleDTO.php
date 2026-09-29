<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class UpdateRoleDTO
{
    public function __construct(
        public string $tenantRoleId,
        public ?string $name = null,
        public ?string $description = null,
        public ?int $status = null,
        /** null = leave unchanged; empty string = clear parent */
        public ?string $parentRoleId = null,
        public bool $parentRoleIdProvided = false,
    ) {}

    public static function fromRequest(string $tenantRoleId, array $validatedData): self
    {
        $parentProvided = array_key_exists('parent_role_id', $validatedData);

        return new self(
            tenantRoleId: $tenantRoleId,
            name: $validatedData['name'] ?? null,
            description: array_key_exists('description', $validatedData)
                ? $validatedData['description']
                : null,
            status: array_key_exists('status', $validatedData)
                ? (int) $validatedData['status']
                : null,
            parentRoleId: $parentProvided
                ? ($validatedData['parent_role_id'] !== null && $validatedData['parent_role_id'] !== ''
                    ? (string) $validatedData['parent_role_id']
                    : null)
                : null,
            parentRoleIdProvided: $parentProvided,
        );
    }
}
