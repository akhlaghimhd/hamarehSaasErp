<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class AssignScopeToUserDTO
{
    public function __construct(
        public string $tenantUserId,
        public array $scopeIds
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            tenantUserId: $validatedData['tenant_user_id'],
            scopeIds: $validatedData['scope_ids']
        );
    }
}