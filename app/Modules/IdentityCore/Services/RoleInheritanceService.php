<?php

namespace App\Modules\IdentityCore\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W2-03 — Live permission inheritance along parent_role_id chain.
 *
 * Effective permissions for a role = own permissions ∪ ancestors' permissions.
 * Cycle detection when setting parent_role_id.
 */
class RoleInheritanceService
{
    /**
     * Expand assigned role ids to include all ancestors (parent chain).
     *
     * @param  list<string>  $roleIds
     * @return list<string>
     */
    public function expandWithAncestors(string $tenantId, array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_filter($roleIds)));
        if ($roleIds === []) {
            return [];
        }

        $parents = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->pluck('parent_role_id', 'tenant_role_id')
            ->all();

        $expanded = [];
        foreach ($roleIds as $rid) {
            $current = $rid;
            $guard = 0;
            while ($current && $guard < 50) {
                $expanded[$current] = true;
                $current = $parents[$current] ?? null;
                $guard++;
            }
        }

        return array_keys($expanded);
    }

    /**
     * Effective permission codes for the given roles including ancestor inheritance.
     *
     * @param  list<string>  $roleIds
     * @return list<string>
     */
    public function permissionCodesForRoles(string $tenantId, array $roleIds): array
    {
        $expanded = $this->expandWithAncestors($tenantId, $roleIds);
        if ($expanded === []) {
            return [];
        }

        return DB::table('tenant_role_permissions')
            ->join(
                'tenant_permissions',
                'tenant_role_permissions.tenant_permission_id',
                '=',
                'tenant_permissions.tenant_permission_id'
            )
            ->where('tenant_role_permissions.tenant_id', $tenantId)
            ->whereIn('tenant_role_permissions.tenant_role_id', $expanded)
            ->whereNull('tenant_role_permissions.deleted_at')
            ->whereNull('tenant_permissions.deleted_at')
            ->where('tenant_permissions.status', 1)
            ->pluck('tenant_permissions.code')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ensure setting parent_role_id does not create a cycle.
     */
    public function assertNoCycle(string $tenantId, string $roleId, ?string $parentRoleId): void
    {
        if ($parentRoleId === null || $parentRoleId === '') {
            return;
        }

        if ($parentRoleId === $roleId) {
            throw new HttpException(422, 'نقش نمی‌تواند والد خودش باشد.');
        }

        $parents = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->pluck('parent_role_id', 'tenant_role_id')
            ->all();

        // Walk from proposed parent upward; if we hit roleId → cycle
        $current = $parentRoleId;
        $guard = 0;
        while ($current && $guard < 50) {
            if ($current === $roleId) {
                throw new HttpException(422, 'تنظیم این والد باعث حلقه در سلسله‌مراتب نقش می‌شود.');
            }
            $current = $parents[$current] ?? null;
            $guard++;
        }

        $exists = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $parentRoleId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            throw new HttpException(422, 'نقش والد در این سازمان یافت نشد.');
        }
    }
}
