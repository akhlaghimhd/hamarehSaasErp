<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Holding platform TEST seeder — plain PHP via base64 parts (no gzip).
 * status column uses smallint 1.
 *
 *   docker compose exec app php artisan db:seed --class=HoldingPlatformTestSeeder
 *
 * owner@demo.local | observer@demo.local | op.a@demo.local | op.b@demo.local
 * branch.a@demo.local → Owner123!  |  staff.a@demo.local → Staff123!
 */
class HoldingPlatformTestSeeder extends Seeder
{
    private const PART_MD5 = [
        'd58aeca34ee4373d48dcc01ea496bfee',
        'c0b1c813df862eef9a4633fe346a0bfb',
        '3d006fc7bf9c4f4f624e6f99a66d5262',
        'f35a13edbb98fd6ff74d1ae05893449e',
        '4febb71d3fd85b81aa90f687a8aedc33',
        'd03f38dc01f8f390da51a53e9ddf6607',
        '8e1067e9fa02de5e07a3600af7b2208d',
        'c097ff32aee2cf42a4024541126c4c0a',
        'c96fb303545b51f865a7cd23b89c9eb4',
        '9301eddaa26ec66a322af86c74af8317',
        'cd1b0e4b9b56e04541d45eb1c1991257'
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
