<?php
declare(strict_types=1);

/**
 * One-shot restore for ADR-ID-ORG-005 ceiling files (avoids truncated GitHub pushes).
 * Usage: docker compose exec app php database/scripts/ceiling/restore_ceiling_files.php
 */
$root = dirname(__DIR__, 2);
$partsDir = __DIR__;

$map = [
    'HoldingAccessService' => $root . '/app/Base/Services/HoldingAccessService.php',
    'ScopeService' => $root . '/app/Modules/IdentityCore/Services/ScopeService.php',
    'UserService' => $root . '/app/Modules/IdentityCore/Services/UserService.php',
];

$mustContain = [
    'HoldingAccessService' => 'actorCeilingCompanyIds',
    'ScopeService' => 'filterScopesByActorCeiling',
    'UserService' => 'resolveHomeCompanyIdForCreate',
];

foreach ($map as $prefix => $out) {
    $b64 = '';
    for ($i = 0; $i < 30; $i++) {
        $f = $partsDir . '/' . $prefix . '.b64.' . $i . '.txt';
        if (! is_file($f)) {
            break;
        }
        $b64 .= preg_replace('/\s+/', '', (string) file_get_contents($f));
    }
    if ($b64 === '') {
        fwrite(STDERR, "Missing parts for {$prefix}\n");
        exit(1);
    }
    $body = base64_decode($b64, true);
    if ($body === false) {
        fwrite(STDERR, "Decode failed for {$prefix}\n");
        exit(1);
    }
    $needle = $mustContain[$prefix];
    if (! str_contains($body, $needle)) {
        fwrite(STDERR, "Decoded {$prefix} missing marker {$needle}\n");
        exit(1);
    }
    if (! is_dir(dirname($out))) {
        fwrite(STDERR, "Target dir missing for {$out}\n");
        exit(1);
    }
    file_put_contents($out, $body);
    echo "OK {$prefix} -> {$out} bytes=" . strlen($body) . PHP_EOL;
}

echo "Done. Run: docker compose exec app php artisan test --filter=HoldingAccessServiceTest\n";
