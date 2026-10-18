<?php
declare(strict_types=1);
/**
 * One-shot: restore HoldingAccessService after accidental PLACEHOLDER and wire ADR-ID-ORG-005 L04.
 * Run from repo root:
 *   php database/scripts/ceiling/restore_has_l04.php
 */
$root = getcwd();
$target = $root . '/app/Base/Services/HoldingAccessService.php';
if (!is_dir($root . '/app/Base/Services')) {
    fwrite(STDERR, "Run from hamarehSaasErp repository root.\n");
    exit(1);
}

$url = 'https://raw.githubusercontent.com/akhlaghimhd/hamarehSaasErp/c5eabb9705133ecb8862b3886984ea63bb07eca9/app/Base/Services/HoldingAccessService.php';
$ctx = stream_context_create(['http' => ['timeout' => 30, 'header' => "User-Agent: ceiling-restore\r\n"]]);
$bin = @file_get_contents($url, false, $ctx);
if ($bin === false || strlen($bin) < 5000) {
    fwrite(STDERR, "Failed to download base HAS from GitHub raw.\n");
    exit(1);
}

$old = <<<'OLD'
        if (!$exists) {
            throw new RuntimeException('دسترسی به این عضو در محدوده شرکت یا شعبه شما مجاز نیست.');
        }
    }

    public function assertCanManageUserId(string $userId): void
OLD;

$new = <<<'NEW'
        if (!$exists) {
            throw new RuntimeException('دسترسی به این عضو در محدوده شرکت یا شعبه شما مجاز نیست.');
        }

        // ADR-ID-ORG-005 L04 — when home_company_id is set, it must sit inside actor ceiling
        $this->assertTargetHomeWithinCeiling($tenantUserId);
    }

    public function assertCanManageUserId(string $userId): void
NEW;

if (strpos($bin, $old) === false) {
    if (strpos($bin, 'assertTargetHomeWithinCeiling($tenantUserId)') !== false) {
        file_put_contents($target, $bin);
        echo "HAS already had L04 wire; wrote base bytes=" . strlen($bin) . PHP_EOL;
        exit(0);
    }
    fwrite(STDERR, "Patch pattern not found in downloaded HAS.\n");
    exit(1);
}

$fixed = str_replace($old, $new, $bin);
file_put_contents($target, $fixed);
echo "OK restored HoldingAccessService.php bytes=" . strlen($fixed) . PHP_EOL;
