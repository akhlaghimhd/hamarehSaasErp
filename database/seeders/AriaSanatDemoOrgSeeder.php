<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Emergency stub after accidental truncate during H-hardening push.
 * Full rich body: restore from git blob ef2fb3303b94c8b64bb9e2b5443cf429e6ac3947
 * H2-05 structure catalog lives in DemoSalesStructureSeeder.
 */
class AriaSanatDemoOrgSeeder extends Seeder
{
    public const SYSTEM_TENANT_ID = '00000000-0000-0000-0000-000000000001';
    public const DEMO_TENANT_ID = '3ab77cac-1343-4b13-8e14-0d887aad132a';

    public function run(): void
    {
        $this->command?->error(
            'AriaSanatDemoOrgSeeder body truncated during H2 push. Restore full file from git blob ef2fb3303b94c8b64bb9e2b5443cf429e6ac3947 then re-seed.'
        );
        $this->command?->warn('Calling DemoSalesStructureSeeder for H2-05 catalog only.');
        $this->call(DemoSalesStructureSeeder::class);
    }
}
