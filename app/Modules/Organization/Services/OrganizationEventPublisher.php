<?php

namespace App\Modules\Organization\Services;

use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ORG-P6-04 / P6-05 / ADR-ORG-002 — Publish integration contracts via event_outbox.
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

    public function publishIntercompanyPartnerUpserted(string $icPartnerId, array $context = []): string
    {
        return $this->publish(
            'intercompany_partner_upserted',
            'erp_intercompany_partners',
            $icPartnerId,
            array_merge(['ic_partner_id' => $icPartnerId], $context)
        );
    }

    public function publishIntercompanyPartnerDeleted(string $icPartnerId, array $context = []): string
    {
        return $this->publish(
            'intercompany_partner_deleted',
            'erp_intercompany_partners',
            $icPartnerId,
            array_merge(['ic_partner_id' => $icPartnerId], $context)
        );
    }

    public function publishIntercompanyRuleUpserted(string $icRuleId, array $context = []): string
    {
        return $this->publish(
            'intercompany_rule_upserted',
            'erp_intercompany_rules',
            $icRuleId,
            array_merge(['ic_rule_id' => $icRuleId], $context)
        );
    }

    public function publishIntercompanyRuleDeleted(string $icRuleId, array $context = []): string
    {
        return $this->publish(
            'intercompany_rule_deleted',
            'erp_intercompany_rules',
            $icRuleId,
            array_merge(['ic_rule_id' => $icRuleId], $context)
        );
    }
}
