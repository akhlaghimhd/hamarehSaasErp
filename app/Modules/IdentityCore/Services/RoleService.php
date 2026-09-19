<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\CreateRoleDTO;
use App\Modules\IdentityCore\DTOs\UpdateRoleDTO;
use App\Modules\IdentityCore\DTOs\AssignRoleToUserDTO;
use App\Modules\IdentityCore\DTOs\AssignPermissionsToRoleDTO;
use App\Modules\IdentityCore\DTOs\CreatePermissionDTO;
use App\Modules\IdentityCore\DTOs\UpdatePermissionDTO;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Base\Support\TenantCache;
use App\Base\Http\Middleware\LoadUserScopesMiddleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class RoleService
{
    public function listRoles(): Collection
    {
        $tenantId = $this->getTenantId();

        return TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'parent:tenant_role_id,name,code',
                'permissions' => function ($q) use ($tenantId) {
                    $q->where('tenant_permissions.tenant_id', $tenantId)
                        ->select(
                            'tenant_permissions.tenant_permission_id',
                            'tenant_permissions.code',
                            'tenant_permissions.name',
                            'tenant_permissions.description'
                        );
                },
            ])
            ->orderBy('name')
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
                'permissions' => function ($q) use ($tenantId) {
                    $q->where('tenant_permissions.tenant_id', $tenantId)
                        ->select(
                            'tenant_permissions.tenant_permission_id',
                            'tenant_permissions.code',
                            'tenant_permissions.name',
                            'tenant_permissions.module_name',
                            'tenant_permissions.description'
                        );
                },
            ])
            ->firstOrFail();
    }

    public function listPermissions(): Collection
    {
        $tenantId = $this->getTenantId();

        return TenantPermission::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('module_name')
            ->orderBy('name')
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
        throw new Exception('ایجاد مجوز از طریق سامانه مجاز نیست. کاتالوگ عملیات فقط توسط تیم توسعه (سیدر) به‌روز می‌شود.');
    }

    public function updatePermission(UpdatePermissionDTO $dto): TenantPermission
    {
        $tenantId = $this->getTenantId();

        if (!$this->currentUserIsTenantOwner($tenantId)) {
            throw new Exception('فقط مالک سازمان می‌تواند عنوان و راهنمای کاربری مجوز را ویرایش کند.');
        }

        return DB::transaction(function () use ($dto, $tenantId) {
            $permission = TenantPermission::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_permission_id', $dto->tenantPermissionId)
                ->firstOrFail();

            $changes = [];

            if ($dto->name !== null) {
                $name = trim($dto->name);
                if ($name === '') {
                    throw new Exception('عنوان مجوز نمی‌تواند خالی باشد.');
                }
                if (mb_strlen($name) > 200) {
                    throw new Exception('عنوان مجوز حداکثر ۲۰۰ کاراکتر است.');
                }
                $changes['name'] = $name;
            }

            if ($dto->description !== null) {
                $desc = trim($dto->description);
                if (mb_strlen($desc) > 500) {
                    throw new Exception('راهنمای کاربری حداکثر ۵۰۰ کاراکتر است.');
                }
                $changes['description'] = $desc === '' ? null : $desc;
            }

            if ($changes === []) {
                return $permission;
            }

            $changes['row_version'] = ((int) ($permission->row_version ?? 1)) + 1;
            $permission->update($changes);

            $this->logEventOutbox(
                $tenantId,
                'tenant_permissions',
                $permission->tenant_permission_id,
                'identity.permission.relabeled.v1',
                [
                    'permission_id' => $permission->tenant_permission_id,
                    'code' => $permission->code,
                    'changes' => $changes,
                ]
            );

            TenantCache::flushTenant($tenantId);

            return $permission->fresh();
        });
    }

    public function softDeletePermission(string $tenantPermissionId): void
    {
        throw new Exception('حذف مجوز از طریق سامانه مجاز نیست. کاتالوگ عملیات فقط توسط تیم توسعه (سیدر) مدیریت می‌شود.');
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

            $roleName = trim($dto->roleName);
            if ($roleName === '') {
                throw new Exception('نام نقش الزامی است.');
            }

            $role = TenantRole::create([
                'tenant_role_id'  => (string) Str::uuid(),
                'tenant_id'       => $tenantId,
                'parent_role_id'  => $dto->parentRoleId,
                'code'            => $this->makeUniqueRoleCode($tenantId, $roleName),
                'name'            => $roleName,
                'description'     => $dto->description,
                'status'          => 1,
            ]);

            if (!empty($dto->permissionIds)) {
                $this->syncRolePermissions($tenantId, $role->tenant_role_id, $dto->permissionIds);
            }

            $this->logEventOutbox(
                $tenantId,
                'tenant_roles',
                $role->tenant_role_id,
                'identity.role.created.v1',
                [
                    'role_id'        => $role->tenant_role_id,
                    'parent_role_id' => $dto->parentRoleId,
                ]
            );

            TenantCache::flushTenant($tenantId);

            return $role->load(['parent:tenant_role_id,name,code', 'permissions']);
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

            return $role->fresh(['parent:tenant_role_id,name,code', 'permissions']);
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

            $userCount = TenantUserRole::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $tenantRoleId)
                ->count();

            if ($userCount > 0) {
                throw new Exception(
                    "این نقش به {$userCount} کاربر تخصیص داده شده است. ابتدا نقش را از کاربران بردارید، سپس حذف کنید."
                );
            }

            $this->invalidateSecurityCachesForRole($tenantId, $tenantRoleId);

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
            throw new Exception('حداقل یک نقش باید انتخاب شود.');
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

            $existingRoleIds = $existing->pluck('tenant_role_id')->all();
            $toAdd = array_diff($roleIds, $existingRoleIds);
            $toRemove = array_diff($existingRoleIds, $roleIds);

            if ($toRemove !== []) {
                TenantUserRole::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $dto->userId)
                    ->whereIn('tenant_role_id', $toRemove)
                    ->delete();
            }

            $last = $existing->first();
            foreach ($toAdd as $roleId) {
                $userRole = TenantUserRole::create([
                    'tenant_user_role_id' => (string) Str::uuid(),
                    'tenant_id'           => $tenantId,
                    'user_id'             => $dto->userId,
                    'tenant_role_id'      => $roleId,
                ]);
                $last = $userRole;
            }

            TenantCache::forget('identity', "user_permissions:{$dto->userId}", $tenantId);
            LoadUserScopesMiddleware::forget($tenantId, $dto->userId);

            if (!$last) {
                $last = TenantUserRole::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $dto->userId)
                    ->firstOrFail();
            }

            return $last;
        });
    }

    public function listRolesForUser(string $userId): Collection
    {
        $tenantId = $this->getTenantId();

        $roleIds = TenantUserRole::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->pluck('tenant_role_id');

        return TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('tenant_role_id', $roleIds)
            ->orderBy('name')
            ->get();
    }

    public function assignPermissionsToRole(AssignPermissionsToRoleDTO $dto): TenantRole
    {
        $tenantId = $this->getTenantId();
        $tenantRoleId = $dto->tenantRoleId;
        $permissionIds = $dto->permissionIds;

        return DB::transaction(function () use ($tenantRoleId, $permissionIds, $tenantId) {
            $role = TenantRole::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $tenantRoleId)
                ->firstOrFail();

            $this->syncRolePermissions($tenantId, $tenantRoleId, $permissionIds);

            $this->logEventOutbox(
                $tenantId,
                'tenant_roles',
                $tenantRoleId,
                'identity.role.permissions_assigned.v1',
                [
                    'role_id' => $tenantRoleId,
                    'permission_ids' => $permissionIds,
                ]
            );

            TenantCache::flushTenant($tenantId);
            $this->invalidateSecurityCachesForRole($tenantId, $tenantRoleId);

            return $role->fresh([
                'permissions' => function ($q) use ($tenantId) {
                    $q->where('tenant_permissions.tenant_id', $tenantId)
                        ->select(
                            'tenant_permissions.tenant_permission_id',
                            'tenant_permissions.code',
                            'tenant_permissions.name',
                            'tenant_permissions.module_name',
                            'tenant_permissions.description'
                        );
                },
            ]);
        });
    }

    private function currentUserIsTenantOwner(string $tenantId): bool
    {
        $userId = auth()->user()?->user_id ?? null;
        if (!$userId) {
            return false;
        }

        return DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where('is_owner', true)
            ->exists();
    }

    private function syncRolePermissions(string $tenantId, string $roleId, array $permissionIds): void
    {
        $permissionIds = array_values(array_unique(array_filter($permissionIds)));

        TenantRolePermission::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleId)
            ->delete();

        foreach ($permissionIds as $permId) {
            TenantPermission::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_permission_id', $permId)
                ->firstOrFail();

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $tenantId,
                'tenant_role_id'            => $roleId,
                'tenant_permission_id'      => $permId,
            ]);
        }
    }

    private function makeUniqueRoleCode(string $tenantId, string $roleName): string
    {
        $base = Str::slug($roleName, '-');
        if ($base === '' || !preg_match('/[a-z0-9]/i', $base)) {
            $base = 'role';
        }
        $base = Str::lower(Str::limit($base, 40, ''));

        $code = $base;
        $i = 2;
        while (
            TenantRole::query()
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->exists()
        ) {
            $suffix = '-' . $i;
            $code = Str::limit($base, 50 - strlen($suffix), '') . $suffix;
            $i++;
            if ($i > 500) {
                $code = 'role-' . Str::lower(Str::random(8));
                break;
            }
        }

        return $code;
    }

    private function invalidateSecurityCachesForRole(string $tenantId, string $roleId): void
    {
        $userIds = TenantUserRole::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleId)
            ->pluck('user_id')
            ->unique()
            ->filter()
            ->values();

        foreach ($userIds as $userId) {
            $uid = (string) $userId;
            TenantCache::forget('identity', "user_permissions:{$uid}", $tenantId);
            try {
                LoadUserScopesMiddleware::forget($tenantId, $uid);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    private function getTenantId(): string
    {
        $tenantId = app()->bound('current_tenant_id')
            ? app('current_tenant_id')
            : null;

        if (!$tenantId) {
            throw new Exception('Tenant context is required.');
        }

        return (string) $tenantId;
    }

    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('event_outbox')) {
                return;
            }

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => $aggregateType,
                'aggregate_id'   => $aggregateId,
                'event_type'     => $eventType,
                'payload'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('event_outbox write failed', [
                'event_type'   => $eventType,
                'aggregate'    => $aggregateType,
                'aggregate_id' => $aggregateId,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
