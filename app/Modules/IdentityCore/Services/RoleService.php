<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\CreateRoleDTO;
use App\Modules\IdentityCore\DTOs\CreatePermissionDTO;
use App\Modules\IdentityCore\DTOs\UpdateRoleDTO;
use App\Modules\IdentityCore\DTOs\AssignRoleToUserDTO;
use App\Modules\IdentityCore\DTOs\AssignPermissionsToRoleDTO;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\IdentityCore\Models\TenantPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class RoleService
{
    public function listRoles(): Collection
    {
        $this->getTenantId();

        return TenantRole::query()
            ->orderBy('code')
            ->get();
    }

    public function getRole(string $tenantRoleId): TenantRole
    {
        $tenantId = $this->getTenantId();

        return TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $tenantRoleId)
            ->firstOrFail();
    }

    public function listPermissions(): Collection
    {
        $this->getTenantId();

        return TenantPermission::query()
            ->where('status', 1)
            ->orderBy('module_name')
            ->orderBy('code')
            ->get();
    }

    public function createPermission(CreatePermissionDTO $dto): TenantPermission
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            $permission = TenantPermission::create([
                'tenant_id'    => $tenantId,
                'code'         => $dto->code,
                'name'         => $dto->name,
                'module_name'  => $dto->moduleName,
                'action_type'  => $dto->actionType,
                'description'  => $dto->description,
                'status'       => 1,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'tenant_permissions',
                $permission->tenant_permission_id,
                'identity.permission.created.v1',
                [
                    'permission_id' => $permission->tenant_permission_id,
                    'code'          => $permission->code,
                    'module_name'   => $permission->module_name,
                ]
            );

            return $permission;
        });
    }

    public function createRole(CreateRoleDTO $dto): TenantRole
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            $role = TenantRole::create([
                'tenant_id'   => $tenantId,
                'code'        => $dto->roleName,
                'name'        => $dto->roleName,
                'description' => $dto->description,
                'status'      => 1,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'tenant_roles',
                $role->tenant_role_id,
                'identity.role.created.v1',
                [
                    'role_id' => $role->tenant_role_id,
                    'code'    => $role->code,
                ]
            );

            if (!empty($dto->permissionIds)) {
                $permissionsDto = new AssignPermissionsToRoleDTO($role->tenant_role_id, $dto->permissionIds);
                $this->assignPermissionsToRole($permissionsDto);
            }

            return $role;
        });
    }

    public function updateRole(UpdateRoleDTO $dto): TenantRole
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            $role = TenantRole::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $dto->tenantRoleId)
                ->firstOrFail();

            $changes = array_filter([
                'name'        => $dto->name,
                'description' => $dto->description,
                'status'      => $dto->status,
            ], fn ($value) => !is_null($value));

            if (!empty($changes)) {
                $changes['row_version'] = ((int) ($role->row_version ?? 1)) + 1;
                $role->update($changes);
            }

            $this->logEventOutbox(
                $tenantId,
                'tenant_roles',
                $role->tenant_role_id,
                'identity.role.updated.v1',
                [
                    'role_id' => $role->tenant_role_id,
                    'changes' => $changes,
                ]
            );

            Cache::tags(["tenant:{$tenantId}"])->flush();

            return $role->fresh();
        });
    }

    public function softDeleteRole(string $tenantRoleId): void
    {
        $tenantId = $this->getTenantId();

        DB::transaction(function () use ($tenantRoleId, $tenantId) {
            $role = TenantRole::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $tenantRoleId)
                ->firstOrFail();

            $role->delete();

            $this->logEventOutbox(
                $tenantId,
                'tenant_roles',
                $tenantRoleId,
                'identity.role.deleted.v1',
                ['role_id' => $tenantRoleId]
            );

            Cache::tags(["tenant:{$tenantId}"])->flush();
        });
    }

    public function assignRoleToUser(AssignRoleToUserDTO $dto): TenantUserRole
    {
        $tenantId = $this->getTenantId();

        $role = TenantRole::where('tenant_role_id', $dto->roleIds[0] ?? null)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return DB::transaction(function () use ($dto, $tenantId, $role) {
            $userRole = TenantUserRole::firstOrCreate([
                'tenant_id'      => $tenantId,
                'user_id'        => $dto->userId,
                'tenant_role_id' => $role->tenant_role_id,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'tenant_user_roles',
                $userRole->tenant_user_role_id ?? (string) Str::uuid(),
                'identity.role.assigned.v1',
                [
                    'user_id'     => $dto->userId,
                    'role_id'     => $role->tenant_role_id,
                    'assigned_at' => now()->toIso8601String(),
                ]
            );

            Cache::tags(["tenant:{$tenantId}"])->forget("user_permissions:{$dto->userId}");

            return $userRole;
        });
    }

    public function assignPermissionsToRole(AssignPermissionsToRoleDTO $dto): void
    {
        $tenantId = $this->getTenantId();

        DB::transaction(function () use ($dto, $tenantId) {
            TenantRolePermission::where('tenant_role_id', $dto->tenantRoleId)
                ->where('tenant_id', $tenantId)
                ->delete();

            $insertData = [];
            foreach ($dto->permissionIds as $permissionId) {
                $insertData[] = [
                    'tenant_role_permission_id' => Str::uuid()->toString(),
                    'tenant_id'                 => $tenantId,
                    'tenant_role_id'            => $dto->tenantRoleId,
                    'tenant_permission_id'      => $permissionId,
                    'created_at'                => now(),
                    'updated_at'                => now(),
                ];
            }

            if (!empty($insertData)) {
                TenantRolePermission::insert($insertData);
            }

            $this->logEventOutbox(
                $tenantId,
                'tenant_roles',
                $dto->tenantRoleId,
                'identity.role.permissions_updated.v1',
                [
                    'role_id'        => $dto->tenantRoleId,
                    'permission_ids' => $dto->permissionIds,
                ]
            );

            Cache::tags(["tenant:{$tenantId}"])->flush();
        });
    }

    private function getTenantId(): string
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        if (!$tenantId) {
            throw new Exception('Tenant Context is missing. Architecture Violation.');
        }

        return $tenantId;
    }

    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
