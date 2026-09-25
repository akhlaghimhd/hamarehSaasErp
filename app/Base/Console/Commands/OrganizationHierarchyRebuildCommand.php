<?php

namespace App\Base\Console\Commands;

use App\Base\Context\TenantContext;
use App\Modules\Organization\Services\HierarchySyncService;
use Illuminate\Console\Command;

class OrganizationHierarchyRebuildCommand extends Command
{
    protected $signature = 'organization:hierarchy-rebuild
                            {tenant_id? : Tenant UUID (optional if already in context)}
                            {--health : Print health after rebuild}';

    protected $description = 'Rebuild system LEGAL/ESTABLISHMENT/PRODUCT hierarchy trees for a tenant (Smart Hierarchy P3)';

    public function handle(HierarchySyncService $sync): int
    {
        $tenantId = $this->argument('tenant_id') ?: TenantContext::getInstance()->getTenantId();
        if (!$tenantId) {
            $this->error('tenant_id is required (argument or TenantContext).');

            return self::FAILURE;
        }

        app()->instance('current_tenant_id', $tenantId);
        TenantContext::getInstance()->setTenantId($tenantId);

        $this->info("Rebuilding system hierarchies for tenant {$tenantId}...");
        $sync->rebuildSystemTreesForTenant($tenantId);
        $ensured = $sync->ensureStructuralTrees($tenantId);
        $this->info('Structural ensure: '.json_encode($ensured, JSON_UNESCAPED_UNICODE));

        if ($this->option('health')) {
            $health = $sync->health($tenantId);
            $this->line('Health: '.$health['status']);
            $this->line(json_encode($health, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
