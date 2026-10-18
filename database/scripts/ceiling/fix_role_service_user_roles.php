<?php
/**
 * One-shot: restore RoleService from last known-good if PLACEHOLDER, then patch
 * listRolesForUser (filter deleted_at) + hard-purge on revoke.
 *
 * Run from repo root (host with git) OR inside container after git pull:
 *   docker compose exec app php database/scripts/ceiling/fix_role_service_user_roles.php
 *
 * Prefer host workflow:
 *   git fetch origin
 *   git checkout 6563bc2d -- app/Modules/IdentityCore/Services/RoleService.php
 *   docker compose exec app php database/scripts/ceiling/fix_role_service_user_roles.php
 *   git add app/Modules/IdentityCore/Services/RoleService.php
 *   git commit -m "fix(identity): hide soft-deleted user roles and hard-purge on revoke"
 *   git push origin develop
 */

$path = getcwd() . '/app/Modules/IdentityCore/Services/RoleService.php';
if (!is_file($path)) {
    $path = dirname(__DIR__, 3) . '/app/Modules/IdentityCore/Services/RoleService.php';
}
if (!is_file($path)) {
    fwrite(STDERR, "RoleService not found at expected path\n");
    exit(1);
}

$text = file_get_contents($path);

if (str_contains($text, 'PLACEHOLDER') || strlen(trim($text)) < 200) {
    fwrite(STDERR, "RoleService is broken/PLACEHOLDER. Restore first:\n");
    fwrite(STDERR, "  git checkout 6563bc2d -- app/Modules/IdentityCore/Services/RoleService.php\n");
    exit(2);
}

if (str_contains($text, 'Only active memberships') && str_contains($text, 'ghost chips')) {
    echo "Already patched\n";
    exit(0);
}

$oldList = <<<'PHP'
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
PHP;

$newList = <<<'PHP'
    public function listRolesForUser(string $userId): Collection
    {
        $tenantId = $this->getTenantId();

        // Only active memberships — soft-deleted rows must never appear in UI chips.
        $roleIds = TenantUserRole::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('tenant_role_id');

        return TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('tenant_role_id', $roleIds)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }
PHP;

$oldRemove = <<<'PHP'
            if ($toRemove !== []) {
                TenantUserRole::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $dto->userId)
                    ->whereIn('tenant_role_id', $toRemove)
                    ->delete();
            }
PHP;

$newRemove = <<<'PHP'
            if ($toRemove !== []) {
                // Hard-delete active + any soft-deleted rows for these roles so list/sync stay consistent
                // (model has no SoftDeletes trait but table has deleted_at).
                DB::table('tenant_user_roles')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $dto->userId)
                    ->whereIn('tenant_role_id', $toRemove)
                    ->delete();
            }

            // Purge soft-deleted rows not in desired set (ghost chips)
            DB::table('tenant_user_roles')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $dto->userId)
                ->whereNotNull('deleted_at')
                ->when($roleIds !== [], function ($q) use ($roleIds) {
                    $q->whereNotIn('tenant_role_id', $roleIds);
                })
                ->delete();
PHP;

if (!str_contains($text, $oldList)) {
    fwrite(STDERR, "listRolesForUser block not found — abort\n");
    exit(3);
}
if (!str_contains($text, $oldRemove)) {
    fwrite(STDERR, "toRemove block not found — abort\n");
    exit(4);
}

$text = str_replace($oldList, $newList, $text);
$text = str_replace($oldRemove, $newRemove, $text);
file_put_contents($path, $text);
echo "Patched $path bytes=" . strlen($text) . "\n";
