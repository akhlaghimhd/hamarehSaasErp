<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Holding platform TEST seeder — base64 parts, status=1, scope_name set.
 *
 *   docker compose exec app php artisan db:seed --class=HoldingPlatformTestSeeder
 *
 * owner@demo.local | observer@demo.local | op.a@demo.local | op.b@demo.local
 * branch.a@demo.local → Owner123!  |  staff.a@demo.local → Staff123!
 */
class HoldingPlatformTestSeeder extends Seeder
{
    private const PART_MD5 = [
        'a3d45dee8056250f78d6f8c840a17054',
        'f51458225a87d44e498ca992a2410e53',
        'a43fed23329e436f6200a287ab20d852',
        '71f872e62c49bc4897134c782ebcf7f1',
        '888e14db3a0ea4adbc7eadc5bfbbc91c',
        '3d42a451b58756dddf9760b50c0040ea',
        '5b40148d61b2fe6c1cfe229b6e104760',
        'd25e2af7c60991b998c598a55a29292c',
        '9949d7616804af9eb69cb2d91ef3ec5a',
        '4f45741ac29682be0c863c99344579da',
        'fe7f59f0349e604e7a4d20f615fa9f19'
    ];

    public function run(): void
    {
        $dir = __DIR__;
        $b64 = '';
        foreach (self::PART_MD5 as $i => $expect) {
            $f = $dir.DIRECTORY_SEPARATOR.'HoldingPlatformTestSeeder.b64.'.$i.'.txt';
            if (! is_file($f)) {
                $this->command?->error('Missing '.$f.' — git pull origin develop');
                return;
            }
            $part = preg_replace('/\s+/', '', (string) file_get_contents($f));
            if (md5($part) !== $expect) {
                $this->command?->error("MD5 mismatch part {$i}");
                return;
            }
            $b64 .= $part;
        }
        $php = base64_decode($b64, true);
        if ($php === false || ! str_contains($php, 'class HoldingPlatformTestSeeder extends Seeder')) {
            $this->command?->error('base64 decode failed');
            return;
        }
        $php = preg_replace(
            '/class HoldingPlatformTestSeeder extends Seeder/',
            'class HoldingPlatformTestSeederBody extends Seeder',
            $php,
            1
        );
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
