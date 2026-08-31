<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class UpdatePermissionDTO
{
    public function __construct(
        public string $tenantPermissionId,
        public ?string $name = null,
        public ?string $moduleName = null,
        public ?string $actionType = null,
        public ?string $description = null,
        public ?int $status = null,
    ) {}

    public static function fromRequest(string $tenantPermissionId, array $validatedData): self
    {
        return new self(
            tenantPermissionId: $tenantPermissionId,
            name: $validatedData['name'] ?? null,
            moduleName: $validatedData['module_name'] ?? null,
            actionType: array_key_exists('action_type', $validatedData)
                ? $validatedData['action_type']
                : null,
            description: array_key_exists('description', $validatedData)
                ? $validatedData['description']
                : null,
            status: array_key_exists('status', $validatedData)
                ? (int) $validatedData['status']
                : null,
        );
    }
}
