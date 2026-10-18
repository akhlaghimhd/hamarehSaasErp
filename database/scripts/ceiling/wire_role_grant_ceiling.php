<?php
/**
 * Wire ADR-ID-ORG-006 into RoleService::assignRoleToUser.
 * Run: docker compose exec app php database/scripts/ceiling/wire_role_grant_ceiling.php
 */
$path = getcwd() . '/app/Modules/IdentityCore/Services/RoleService.php';
if (! is_file($path)) {
    $path = dirname(__DIR__, 3) . '/app/Modules/IdentityCore/Services/RoleService.php';
}
if (! is_file($path)) {
    fwrite(STDERR, "RoleService not found\n");
    exit(1);
}

$text = file_get_contents($path);
if (str_contains($text, 'RoleGrantCeilingService')) {
    echo "Already wired\n";
    exit(0);
}
if (str_contains($text, 'PLACEHOLDER') || strlen(trim($text)) < 200) {
    fwrite(STDERR, "RoleService is PLACEHOLDER — restore first\n");
    exit(2);
}

$needle = <<<'PHP'
        $toAdd = array_values(array_diff($desired, $existingRoleIds));
        $toRemove = array_values(array_diff($existingRoleIds, $desired));

        $requireApproval = app(TenantIdentitySettingsService::class)
PHP;

$insert = <<<'PHP'
        $toAdd = array_values(array_diff($desired, $existingRoleIds));
        $toRemove = array_values(array_diff($existingRoleIds, $desired));

        // ADR-ID-ORG-006 — hierarchical role grant ceiling (hard block on new grants)
        if ($toAdd !== []) {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds($tenantId, $dto->userId, $toAdd);
        }

        $requireApproval = app(TenantIdentitySettingsService::class)
PHP;

if (! str_contains($text, $needle)) {
    fwrite(STDERR, "assignRoleToUser needle not found — abort\n");
    exit(3);
}

$text = str_replace($needle, $insert, $text);
file_put_contents($path, $text);
echo "Wired RoleGrantCeilingService into RoleService bytes=" . strlen($text) . "\n";
