<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Holding platform TEST reset seeder (self-extracting).
 *
 * Usage:
 *   docker compose exec app php artisan db:seed --class=PermissionSeeder
 *   docker compose exec app php artisan db:seed --class=HoldingPlatformTestSeeder
 *
 * Accounts: owner@demo.local, observer@demo.local, op.a@demo.local, op.b@demo.local,
 *           branch.a@demo.local (Owner123!) ; staff.a@demo.local (Staff123!)
 */
class HoldingPlatformTestSeeder extends Seeder
{
    public function run(): void
    {
        $dir = __DIR__;
        $b64 = '';
        foreach (['HoldingPlatformTestSeeder.b64.0.txt', 'HoldingPlatformTestSeeder.b64.1.txt'] as $name) {
            $f = $dir.DIRECTORY_SEPARATOR.$name;
            if (! is_file($f)) {
                $this->command?->error('Missing '.$name);

                return;
            }
            $b64 .= preg_replace('/\s+/', '', (string) file_get_contents($f));
        }
        $gz = base64_decode($b64, true);
        if ($gz === false) {
            $this->command?->error('base64 decode failed');

            return;
        }
        $php = gzdecode($gz);
        if ($php === false || ! str_contains($php, 'class HoldingPlatformTestSeederBody')) {
            $this->command?->error('gzdecode failed or unexpected body');

            return;
        }
        $tmp = sys_get_temp_dir().'/HPTS_'.md5($php).'.php';
        file_put_contents($tmp, $php);
        require_once $tmp;
        $impl = new HoldingPlatformTestSeederBody();
        if (method_exists($impl, 'setCommand') && $this->command) {
            $impl->setCommand($this->command);
        }
        $impl->run();
    }
}
