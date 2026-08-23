<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class CreatePermissionDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public string $moduleName,
        public ?string $actionType = null,
        public ?string $description = null,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            code: $validatedData['code'],
            name: $validatedData['name'],
            moduleName: $validatedData['module_name'],
            actionType: $validatedData['action_type'] ?? null,
            description: $validatedData['description'] ?? null,
        );
    }
}