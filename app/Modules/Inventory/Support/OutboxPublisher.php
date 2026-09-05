<?php

namespace App\Modules\Inventory\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * L6-INV-12 — Centralized transactional outbox writer for Inventory.
 * Writes only; processing is done by Base EventBus / ProcessOutboxMessageJob.
 */
final class OutboxPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function publish(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): string {
        $eventId = (string) Str::uuid();

        DB::table('event_outbox')->insert([
            'event_id'       => $eventId,
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload, JSON_THROW_ON_ERROR),
            'status'         => 1,
            'retry_count'    => 0,
            'created_at'     => now(),
        ]);

        return $eventId;
    }
}
