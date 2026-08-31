<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class UpdateRoleDTO
{
    public function __construct(
        public string $tenantRoleId,
        public ?string $name = null,
        public ?string $description = null,
        public ?int $status = null,
    ) {}

    public static function fromRequest(string $tenantRoleId, array $validatedData): self
    {
        return new self(
            tenantRoleId: $tenantRoleId,
            name: $validatedData['name'] ?? null,
            description: array_key_exists('description', $validatedData)
                ? $validatedData['description']
                : null,
            status: array_key_exists('status', $validatedData)
                ? (int) $validatedData['status']
                : null,
        );
    }
}
