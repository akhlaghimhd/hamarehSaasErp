<?php
$dir = __DIR__ . "/matrix_test_b64";
$b64 = "";
for ($i = 0; $i < 20; $i++) {
    $f = "$dir/part{$i}.b64";
    if (!is_file($f)) break;
    $b64 .= trim(file_get_contents($f));
}
$php = base64_decode($b64);
$path = getcwd() . "/tests/Feature/Modules/IdentityCore/HoldingAccessSecurityMatrixTest.php";
if (!is_dir(dirname($path))) {
  $path = dirname(__DIR__, 3) . "/tests/Feature/Modules/IdentityCore/HoldingAccessSecurityMatrixTest.php";
}
if ($php === false || !str_contains($php, "class HoldingAccessSecurityMatrixTest")) {
  fwrite(STDERR, "assemble failed\n");
  exit(1);
}
file_put_contents($path, $php);
echo "Installed $path bytes=" . strlen($php) . "\n";
