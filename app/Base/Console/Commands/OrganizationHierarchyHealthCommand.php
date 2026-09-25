<?php

namespace App\Base\Console\Commands;

use App\Base\Context\TenantContext;
use App\Modules\Organization\Services\HierarchySyncService;
use Illuminate\Console\Command;

class OrganizationHierarchyHealthCommand extends Command
{
    protected $signature = 'organization:hierarchy-health
                            {tenant_id? : Tenant UUID}';

    protected $description = 'Report system hierarchy health for a tenant (Smart Hierarchy P3)';

    public function handle(HierarchySyncService $sync): int
    {
        $tenantId = $this->argument('tenant_id') ?: TenantContext::getInstance()->getTenantId();
        if (!$tenantId) {
            $this->error('tenant_id is required (argument or TenantContext).');

            return self::FAILURE;
        }

        app()->instance('current_tenant_id', $tenantId);
        TenantContext::getInstance()->setTenantId($tenantId);

        $health = $sync->health($tenantId);
        $this->line('Status: '.$health['status']);
        $this->line(json_encode($health, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $health['status'] === HierarchySyncService::STATUS_HEALTHY
            ? self::SUCCESS
            : self::FAILURE;
    }
}
