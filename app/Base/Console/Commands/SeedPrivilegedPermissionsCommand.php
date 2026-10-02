<?php

namespace App\Base\Console\Commands;

use Database\Seeders\PrivilegedPermissionBootstrap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds identity.privileged.view|request|approve for every tenant.
 * Safe to run from PowerShell / Docker without shell-escape issues.
 */
class SeedPrivilegedPermissionsCommand extends Command
{
    protected $signature = 'erp:seed-privileged-permissions';

    protected $description = 'Ensure identity.privileged.* permissions exist for all tenants';

    public function handle(): int
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable('tenant_permissions')) {
            $this->error('Required tables missing (tenants / tenant_permissions).');

            return self::FAILURE;
        }

        $tenantIds = DB::table('tenants')->pluck('tenant_id');
        if ($tenantIds->isEmpty()) {
            $this->warn('No tenants found.');

            return self::SUCCESS;
        }

        $n = 0;
        foreach ($tenantIds as $tenantId) {
            $tid = (string) $tenantId;
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tid]);
            PrivilegedPermissionBootstrap::ensureForTenant($tid);
            $this->line("ok {$tid}");
            $n++;
        }

        $this->info("Privileged permissions ensured for {$n} tenant(s).");

        return self::SUCCESS;
    }
}
