<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Catalog\DefaultRoleCatalog;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds default roles + permission bindings + active SoD rules for a tenant.
 *
 * Idempotent:
 * - Creates missing system roles (is_system_default=true)
 * - Does NOT wipe custom permission sets on existing non-empty role bindings
 *   unless $forceResyncPermissions is true
 * - Creates missing SoD rules by stable code; does not reactivate soft-deleted
 *   rules the admin intentionally removed
 */
class TenantRbacBootstrapService
{
    public function __construct(
        private readonly ?FeatureCatalogService $features = null
    ) {
    }

    /**
     * @return array{roles_created: int, roles_updated: int, sod_created: int, skipped_roles: list<string>}
     */
    public function bootstrapTenant(string $tenantId, bool $forceResyncPermissions = false): array
    {
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        $permMap = $this->loadPermissionMap($tenantId);
        $enabledFeatures = $this->enabledFeatures($tenantId);
        $hasParent = Schema::hasColumn('tenant_roles', 'parent_role_id');
        $hasPrivileged = Schema::hasColumn('tenant_roles', 'is_privileged');
        $hasSystemDefault = Schema::hasColumn('tenant_roles', 'is_system_default');

        $roleIds = [];
        $created = 0;
        $updated = 0;
        $skipped = [];

        $defs = DefaultRoleCatalog::roles();

        usort($defs, static function (array $a, array $b): int {
            $ag = ($a['is_group'] ?? false) ? 0 : 1;
            $bg = ($b['is_group'] ?? false) ? 0 : 1;
            if ($ag !== $bg) {
                return $ag <=> $bg;
            }

            return strcmp($a['code'], $b['code']);
        });

        foreach ($defs as $def) {
            if (!$this->isRoleEligible($def, $permMap, $enabledFeatures)) {
                $skipped[] = $def['code'];
                continue;
            }

            $parentId = null;
            if (!empty($def['parent_code']) && $hasParent) {
                $parentId = $roleIds[$def['parent_code']]
                    ?? $this->findRoleId($tenantId, $def['parent_code']);
            }

            $existing = DB::table('tenant_roles')
                ->where('tenant_id', $tenantId)
                ->where('code', $def['code'])
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                $roleId = (string) $existing->tenant_role_id;
                $update = [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'status' => 1,
                    'updated_at' => now(),
                ];
                if ($hasParent) {
                    $update['parent_role_id'] = $parentId;
                }
                if ($hasPrivileged) {
                    $update['is_privileged'] = (bool) ($def['is_privileged'] ?? false);
                }
                if ($hasSystemDefault) {
                    $update['is_system_default'] = true;
                }
                DB::table('tenant_roles')->where('tenant_role_id', $roleId)->update($update);
                $updated++;
            } else {
                $roleId = (string) Str::uuid();
                $insert = [
                    'tenant_role_id' => $roleId,
                    'tenant_id' => $tenantId,
                    'code' => $def['code'],
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'row_version' => 1,
                ];
                if ($hasParent) {
                    $insert['parent_role_id'] = $parentId;
                }
                if ($hasPrivileged) {
                    $insert['is_privileged'] = (bool) ($def['is_privileged'] ?? false);
                }
                if ($hasSystemDefault) {
                    $insert['is_system_default'] = true;
                }
                DB::table('tenant_roles')->insert($insert);
                $created++;
            }

            $roleIds[$def['code']] = $roleId;

            if (!($def['is_group'] ?? false)) {
                $this->syncRolePermissions(
                    $tenantId,
                    $roleId,
                    $def['permission_codes'] ?? [],
                    $permMap,
                    $forceResyncPermissions
                );
            }
        }

        $sodCreated = $this->seedSodRules($tenantId, $roleIds);

        return [
            'roles_created' => $created,
            'roles_updated' => $updated,
            'sod_created' => $sodCreated,
            'skipped_roles' => $skipped,
        ];
    }

    private function isRoleEligible(array $def, array $permMap, array $enabledFeatures): bool
    {
        $feature = $def['required_feature'] ?? null;
        if ($feature !== null && $feature !== '') {
            if (!Schema::hasTable('tenant_feature_entitlements')) {
                return false;
            }
            if (!in_array($feature, $enabledFeatures, true)) {
                return false;
            }
        }

        $prefix = $def['required_any_permission_prefix'] ?? null;
        if ($prefix !== null && $prefix !== '') {
            $has = false;
            foreach (array_keys($permMap) as $code) {
                if (str_starts_with((string) $code, $prefix)) {
                    $has = true;
                    break;
                }
            }
            if (!$has) {
                return false;
            }
        }

        return true;
    }

    private function syncRolePermissions(
        string $tenantId,
        string $roleId,
        array $permissionCodes,
        array $permMap,
        bool $force
    ): void {
        $existingCount = (int) DB::table('tenant_role_permissions')
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleId)
            ->count();

        if ($existingCount > 0 && !$force) {
            $existingPermIds = DB::table('tenant_role_permissions')
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $roleId)
                ->pluck('tenant_permission_id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $existingSet = array_flip($existingPermIds);

            $rows = [];
            foreach ($permissionCodes as $code) {
                if (!isset($permMap[$code])) {
                    continue;
                }
                $pid = $permMap[$code];
                if (isset($existingSet[$pid])) {
                    continue;
                }
                $rows[] = [
                    'tenant_role_permission_id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'tenant_role_id' => $roleId,
                    'tenant_permission_id' => $pid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($rows !== []) {
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table('tenant_role_permissions')->insert($chunk);
                }
            }

            return;
        }

        if ($force && $existingCount > 0) {
            DB::table('tenant_role_permissions')
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $roleId)
                ->delete();
        }

        $rows = [];
        foreach ($permissionCodes as $code) {
            if (!isset($permMap[$code])) {
                continue;
            }
            $rows[] = [
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'tenant_role_id' => $roleId,
                'tenant_permission_id' => $permMap[$code],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows !== []) {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('tenant_role_permissions')->insert($chunk);
            }
        }
    }

    private function seedSodRules(string $tenantId, array $roleIds): int
    {
        if (!Schema::hasTable('tenant_sod_rules')) {
            return 0;
        }

        $created = 0;
        foreach (DefaultRoleCatalog::sodRules() as $rule) {
            $aCode = $rule['role_a_code'];
            $bCode = $rule['role_b_code'];
            $aId = $roleIds[$aCode] ?? $this->findRoleId($tenantId, $aCode);
            $bId = $roleIds[$bCode] ?? $this->findRoleId($tenantId, $bCode);
            if (!$aId || !$bId) {
                continue;
            }

            if (strcmp($aId, $bId) > 0) {
                [$aId, $bId] = [$bId, $aId];
            }

            $exists = DB::table('tenant_sod_rules')
                ->where('tenant_id', $tenantId)
                ->where('code', $rule['code'])
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                continue;
            }

            $pairExists = DB::table('tenant_sod_rules')
                ->where('tenant_id', $tenantId)
                ->where('role_a_id', $aId)
                ->where('role_b_id', $bId)
                ->whereNull('deleted_at')
                ->exists();
            if ($pairExists) {
                continue;
            }

            DB::table('tenant_sod_rules')->insert([
                'sod_rule_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'role_a_id' => $aId,
                'role_b_id' => $bId,
                'code' => $rule['code'],
                'name' => $rule['name'],
                'description' => $rule['description'] ?? null,
                'severity' => (int) ($rule['severity'] ?? 3),
                'enforcement' => strtoupper((string) ($rule['enforcement'] ?? 'BLOCK')),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'row_version' => 1,
            ]);
            $created++;
        }

        return $created;
    }

    private function findRoleId(string $tenantId, string $code): ?string
    {
        $id = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->value('tenant_role_id');

        return $id ? (string) $id : null;
    }

    /** @return array<string, string> */
    private function loadPermissionMap(string $tenantId): array
    {
        $map = [];
        if (!Schema::hasTable('tenant_permissions')) {
            return $map;
        }
        $rows = DB::table('tenant_permissions')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get(['code', 'tenant_permission_id']);
        foreach ($rows as $row) {
            $map[(string) $row->code] = (string) $row->tenant_permission_id;
        }

        return $map;
    }

    /** @return list<string> */
    private function enabledFeatures(string $tenantId): array
    {
        if (!Schema::hasTable('tenant_feature_entitlements')) {
            return [];
        }

        try {
            $svc = $this->features ?? app(FeatureCatalogService::class);

            return $svc->enabledCodesForTenant($tenantId);
        } catch (\Throwable) {
            return TenantFeatureEntitlementSafe::enabledCodes($tenantId);
        }
    }
}

final class TenantFeatureEntitlementSafe
{
    /** @return list<string> */
    public static function enabledCodes(string $tenantId): array
    {
        if (!Schema::hasTable('tenant_feature_entitlements')) {
            return [];
        }

        return DB::table('tenant_feature_entitlements')
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->whereNull('deleted_at')
            ->pluck('feature_code')
            ->map(fn ($c) => (string) $c)
            ->unique()
            ->values()
            ->all();
    }
}
