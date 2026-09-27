<?php

namespace App\Modules\Organization\Jobs;

use App\Base\Context\TenantContext;
use App\Modules\Organization\Services\HierarchySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Smart Hierarchy D5: queued full rebuild of system trees for a tenant.
 */
class RebuildSystemHierarchiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public string $tenantId)
    {
    }

    public function handle(HierarchySyncService $sync): void
    {
        if ($this->tenantId === '') {
            return;
        }
        TenantContext::getInstance()->setTenantId($this->tenantId);
        app()->instance('current_tenant_id', $this->tenantId);

        Log::info('RebuildSystemHierarchiesJob start', ['tenant_id' => $this->tenantId]);
        $rebuild = $sync->rebuildSystemTreesForTenant($this->tenantId);
        $sync->ensureStructuralTrees($this->tenantId);
        Log::info('RebuildSystemHierarchiesJob done', ['tenant_id' => $this->tenantId, 'rebuild' => $rebuild]);
    }
}
