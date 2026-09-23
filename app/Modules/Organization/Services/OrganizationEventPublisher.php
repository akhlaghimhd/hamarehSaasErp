<?php

namespace App\Modules\Organization\Services;

use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ORG-P6-04 / P6-05 — Publish integration contracts toward Accounting via event_outbox.
 * Does not implement posting; only emits versioned event envelopes.
 */
class OrganizationEventPublisher
{
    public function publish(string $eventTypeKey, string $aggregateType, string $aggregateId, array $payload): string
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $eventType = config("organization.events.{$eventTypeKey}", $eventTypeKey);
        $eventId = (string) Str::uuid();

        DB::table('event_outbox')->insert([
            'event_id'       => $eventId,
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode(array_merge($payload, [
                'event_type' => $eventType,
                'tenant_id'  => $tenantId,
                'emitted_at' => now()->toIso8601String(),
            ])),
            'status'         => 1,
            'retry_count'    => 0,
            'created_at'     => now(),
        ]);

        return $eventId;
    }

    public function publishEliminationRequested(string $companyId, array $context = []): string
    {
        return $this->publish(
            'elimination_requested',
            'erp_companies',
            $companyId,
            array_merge(['company_id' => $companyId], $context)
        );
    }

    public function publishConsolidationSnapshotted(string $consolRunId, array $context = []): string
    {
        return $this->publish(
            'consolidation_snapshotted',
            'erp_consolidation_runs',
            $consolRunId,
            array_merge(['consol_run_id' => $consolRunId], $context)
        );
    }
}
