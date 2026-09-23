<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\ConsolidationRun;
use App\Modules\Organization\Models\OrgHierarchy;
use App\Modules\Organization\Models\OrgHierarchyNode;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConsolidationRunService
{
    public function __construct(
        protected OrganizationEventPublisher $events = new OrganizationEventPublisher()
    ) {
    }

    public function createDraft(
        string $code,
        string $name,
        ?string $hierarchyId = null,
        ?string $periodStart = null,
        ?string $periodEnd = null,
        ?string $rateSetRef = null,
    ): ConsolidationRun {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if (ConsolidationRun::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new \Exception('کد اجرای تلفیق تکراری است.');
        }

        if ($hierarchyId) {
            OrgHierarchy::where('tenant_id', $tenantId)->where('hierarchy_id', $hierarchyId)->firstOrFail();
        }

        return ConsolidationRun::create([
            'consol_run_id' => (string) Str::uuid(),
            'tenant_id'     => $tenantId,
            'hierarchy_id'  => $hierarchyId,
            'code'          => $code,
            'name'          => $name,
            'period_start'  => $periodStart,
            'period_end'    => $periodEnd,
            'rate_set_ref'  => $rateSetRef,
            'status'        => ConsolidationRun::STATUS_DRAFT,
            'row_version'   => 1,
        ]);
    }

    public function snapshot(string $consolRunId): ConsolidationRun
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $run = ConsolidationRun::where('tenant_id', $tenantId)
            ->where('consol_run_id', $consolRunId)
            ->firstOrFail();

        $nodes = [];
        if ($run->hierarchy_id) {
            $nodes = OrgHierarchyNode::where('tenant_id', $tenantId)
                ->where('hierarchy_id', $run->hierarchy_id)
                ->orderBy('sort_order')
                ->get(['node_id', 'parent_node_id', 'entity_type', 'entity_id', 'sort_order'])
                ->toArray();
        }

        return DB::transaction(function () use ($run, $nodes) {
            $run->update([
                'status'           => ConsolidationRun::STATUS_SNAPSHOTTED,
                'snapshot_payload' => ['nodes' => $nodes, 'captured_at' => now()->toIso8601String()],
                'row_version'      => ((int) ($run->row_version ?? 1)) + 1,
            ]);

            $fresh = $run->fresh();
            $this->events->publishConsolidationSnapshotted($fresh->consol_run_id, [
                'hierarchy_id' => $fresh->hierarchy_id,
                'node_count'   => count($nodes),
            ]);

            return $fresh;
        });
    }
}
