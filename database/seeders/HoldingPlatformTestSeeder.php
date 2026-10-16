<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Holding platform TEST reset seeder.
 *
 * 1) Hard-wipes identity + org (+ finance docs/accounts) for the demo tenant
 * 2) Rebuilds a clean holding structure for manual QA scenarios
 *
 * Usage:
 *   docker compose exec app php artisan db:seed --class=PermissionSeeder
 *   docker compose exec app php artisan db:seed --class=HoldingPlatformTestSeeder
 *
 * SEE ARTIFACT: HoldingPlatformTestSeeder.php in project artifacts if this file is stub-only.
 * Full implementation is maintained in workspace artifacts and must be copied to this path.
 */
class HoldingPlatformTestSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('database/seeders/HoldingPlatformTestSeeder.FULL.php');
        if (is_file($path)) {
            require $path;

            return;
        }

        $this->command?->error(
            'HoldingPlatformTestSeeder full body missing. Copy artifacts/HoldingPlatformTestSeeder.php to database/seeders/HoldingPlatformTestSeeder.php'
        );
    }
}
