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
        $b64File = __DIR__.DIRECTORY_SEPARATOR.'HoldingPlatformTestSeeder.gz.b64';
        if (! is_file($b64File)) {
            $this->command?->error('Missing HoldingPlatformTestSeeder.gz.b64');

            return;
        }
        $gz = base64_decode(preg_replace('/\s+/', '', (string) file_get_contents($b64File)), true);
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
