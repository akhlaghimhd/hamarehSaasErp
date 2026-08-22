<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class AssignPermissionsToRoleDTO
{
    public function __construct(
        public string $tenantRoleId,
        public array $permissionIds
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            tenantRoleId: $data['tenant_role_id'],
            permissionIds: $data['permission_ids'] ?? []
        );
    }
}