<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\CreateRoleDTO;
use App\Modules\IdentityCore\DTOs\CreatePermissionDTO;
use App\Modules\IdentityCore\DTOs\UpdateRoleDTO;
use App\Modules\IdentityCore\DTOs\UpdatePermissionDTO;
use App\Modules\IdentityCore\DTOs\AssignRoleToUserDTO;
use App\Modules\IdentityCore\DTOs\AssignPermissionsToRoleDTO;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\IdentityCore\Models\TenantPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Base\Support\TenantCache;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class RoleService
{
    public function listRoles(): Collection
    {
        $this->getTenantId();

        return TenantRole::query()
            ->with(['parent:tenant_role_id,name,code', 'permissions:tenant_permission_id,code,name,description'])
            ->orderBy('code')
            ->get();
    }

    public function getRole(string $tenantRoleId): TenantRole
    {
        $tenantId = $this->getTenantId();

        return TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $tenantRoleId)
            ->with([
                'parent:tenant_role_id,name,code',
                'children:tenant_role_id,parent_role_id,name,code,status',
                'permissions:tenant_permission_id,code,name,module_name,description',
            ])
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

    public function getPermission(string $tenantPermissionId): TenantPermission
    {
        $tenantId = $this->getTenantId();

        return TenantPermission::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_permission_id', $tenantPermissionId)
            ->firstOrFail();
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
                ]
            );

            TenantCache::flushTenant($tenantId);

            return $permission;
        });
    }

    public function updatePermission(UpdatePermissionDTO $dto): TenantPermission
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            $permission = TenantPermission::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_permission_id', $dto->tenantPermissionId)
                ->firstOrFail();

            $changes = array_filter([
                'name'        => $dto->name,
                'module_name' => $dto->moduleName,
                'action_type' => $dto->actionType,
                'description' => $dto->description,
                'status'      => $dto->status,
            ], fn ($value) => !is_null($value));

            if (!empty($changes)) {
                $changes['row_version'] = ((int) ($permission->row_version ?? 1)) + 1;
                $permission->update($changes);
            }

            $this->logEventOutbox(
                $tenantId,
                'tenant_permissions',
                $permission->tenant_permission_id,
                'identity.permission.updated.v1',
                [
                    'permission_id' => $permission->tenant_permission_id,
                    'changes'       => $changes,
                ]
            );

            TenantCache::flushTenant($tenantId);

            return $permission->fresh();
        });
    }

    public function softDeletePermission(string $tenantPermissionId): void
    {
        $tenantId = $this->getTenantId();

        DB::transaction(function () use ($tenantPermissionId, $tenantId) {
            $permission = TenantPermission::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_permission_id', $tenantPermissionId)
                ->firstOrFail();

            $permission->delete();

            $this->logEventOutbox(
                $tenantId,
                'tenant_permissions',
                $tenantPermissionId,
                'identity.permission.deleted.v1',
                ['permission_id' => $tenantPermissionId]
            );

            TenantCache::flushTenant($tenantId);
        });
    }

    public function createRole(CreateRoleDTO $dto): TenantRole
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            if ($dto->parentRoleId) {
                TenantRole::query()
                    ->where('tenant_id', $tenantId)
                    ->where('tenant_role_id', $dto->parentRoleId)
                    ->firstOrFail();
            }

            $role = TenantRole::create([
                'tenant_id'       => $tenantId,
                'parent_role_id'  => $dto->parentRoleId,
                'code'            => $dto->roleName,
                'name'            => $dto->roleName,
                'description'     => $dto->description,
                'status'          => 1,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'tenant_roles',
                $role->tenant_role_id,
                'identity.role.created.v1',
                [
                    'role_id'         => $role->tenant_role_id,
                    'code'            => $role->code,
                    'parent_role_id'  => $dto->parentRoleId,
                ]
            );

            if (!empty($dto->permissionIds)) {
                $permissionsDto = new AssignPermissionsToRoleDTO($role->tenant_role_id, $dto->permissionIds);
                $this->assignPermissionsToRole($permissionsDto);
            }

            TenantCache::flushTenant($tenantId);

            return $role->load(['parent:tenant_role_id,name,code', 'permissions:tenant_permission_id,code,name,description']);
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

            TenantCache::flushTenant($tenantId);

            return $role->fresh(['parent:tenant_role_id,name,code', 'permissions:tenant_permission_id,code,name,description']);
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

            if (!empty($role->is_system_default)) {
                throw new Exception('نقش سیستمی قابل حذف نیست.');
            }

            $childCount = TenantRole::query()
                ->where('tenant_id', $tenantId)
                ->where('parent_role_id', $tenantRoleId)
                ->count();

            if ($childCount > 0) {
                throw new Exception('این نقش دارای زیرنقش است. ابتدا زیرنقش‌ها را منتقل یا حذف کنید.');
            }

            TenantUserRole::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $tenantRoleId)
                ->delete();

            TenantRolePermission::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $tenantRoleId)
                ->delete();

            $role->delete();

            $this->logEventOutbox(
                $tenantId,
                'tenant_roles',
                $tenantRoleId,
                'identity.role.deleted.v1',
                ['role_id' => $tenantRoleId]
            );

            TenantCache::flushTenant($tenantId);
        });
    }

    public function assignRoleToUser(AssignRoleToUserDTO $dto): TenantUserRole
    {
        $tenantId = $this->getTenantId();
        $roleIds = array_values(array_unique(array_filter($dto->roleIds)));

        if ($roleIds === []) {
            throw new Exception('At least one role_id is required.');
        }

        return DB::transaction(function () use ($dto, $tenantId, $roleIds) {
            foreach ($roleIds as $roleId) {
                TenantRole::where('tenant_role_id', $roleId)
                    ->where('tenant_id', $tenantId)
                    ->firstOrFail();
            }

            $existing = TenantUserRole::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $dto->userId)
                ->get();

            $existingIds = $existing->pluck('tenant_role_id')->all();
            $toAdd = array_diff($roleIds, $existingIds);
            $toRemove = array_diff($existingIds, $roleIds);

            foreach ($toRemove as $removeId) {
                TenantUserRole::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $dto->userId)
                    ->where('tenant_role_id', $removeId)
                    ->delete();

                $this->logEventOutbox(
                    $tenantId,
                    'tenant_user_roles',
                    (string) Str::uuid(),
                    'identity.role.unassigned.v1',
                    [
                        'user_id' => $dto->userId,
                        'role_id' => $removeId,
                    ]
                );
            }

            $last = $existing->first();
            foreach ($toAdd as $roleId) {
                $userRole = TenantUserRole::create([
                    'tenant_user_role_id' => (string) Str::uuid(),
                    'tenant_id'           => $tenantId,
                    'user_id'             => $dto->userId,
                    'tenant_role_id'      => $roleId,
                ]);

                $this->logEventOutbox(
                    $tenantId,
                    'tenant_user_roles',
                    $userRole->tenant_user_role_id ?? (string) Str::uuid(),
                    'identity.role.assigned.v1',
                    [
                        'user_id'     => $dto->userId,
                        'role_id'     => $roleId,
                        'assigned_at' => now()->toIso8601String(),
                    ]
                );
                $last = $userRole;
            }

            TenantCache::forget('identity', "user_permissions:{$dto->userId}", $tenantId);

            if ($last === null) {
                $last = TenantUserRole::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $dto->userId)
                    ->whereIn('tenant_role_id', $roleIds)
                    ->firstOrFail();
            }

            return $last;
        });
    }

    /**
     * @return Collection<int, TenantRole>
     */
    public function listRolesForUser(string $userId): Collection
    {
        $tenantId = $this->getTenantId();

        $roleIds = TenantUserRole::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->pluck('tenant_role_id');

        if ($roleIds->isEmpty()) {
            return new Collection();
        }

        return TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('tenant_role_id', $roleIds)
            ->orderBy('name')
            ->get();
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

            TenantCache::flushTenant($tenantId);
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
