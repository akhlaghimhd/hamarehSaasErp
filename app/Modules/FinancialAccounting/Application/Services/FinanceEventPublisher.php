<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * FIN-P3-06 + FIN-HOLD-W6/L15 — Transactional outbox for finance domain events.
 * Sensitive success path: post, reverse, delete (draft soft-delete).
 */
class FinanceEventPublisher
{
    public const JOURNAL_POSTED = 'finance.journal.posted.v1';

    public const JOURNAL_REVERSED = 'finance.journal.reversed.v1';

    /** Soft-delete of DRAFT journal (FIN-HOLD-W6 L15). */
    public const JOURNAL_DELETED = 'finance.journal.deleted.v1';

    public function publish(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        try {
            if (! Schema::hasTable('event_outbox')) {
                return;
            }

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => $aggregateType,
                'aggregate_id'   => $aggregateId,
                'event_type'     => $eventType,
                'payload'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status'         => 1,
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // best-effort — never break primary transaction path for outbox
        }
    }
}
