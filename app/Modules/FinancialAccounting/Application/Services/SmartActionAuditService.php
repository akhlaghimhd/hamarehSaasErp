<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Infrastructure\Models\SmartActionLog;
use Illuminate\Support\Str;

/** FIN-X-05 — every smart action logged with actor + decision. */
class SmartActionAuditService
{
    public function log(
        string $featureCode,
        string $actionType,
        ?string $decision = null,
        ?array $payload = null,
        ?string $actorId = null,
        ?string $relatedType = null,
        ?string $relatedId = null,
    ): SmartActionLog {
        $tenantId = TenantContext::getInstance()->getTenantId() ?? '00000000-0000-0000-0000-000000000000';

        $actor = $this->normalizeUuid($actorId);
        $related = $this->normalizeUuid($relatedId);

        $row = new SmartActionLog();
        $row->forceFill([
            'smart_action_log_id' => (string) Str::uuid(),
            'tenant_id'           => $tenantId,
            'action_type'         => $actionType,
            'feature_code'        => $featureCode,
            'actor_id'            => $actor,
            'decision'            => $decision,
            'payload'             => $payload,
            'related_entity_type' => $relatedType,
            'related_entity_id'   => $related,
            'created_at'          => now(),
        ]);
        $row->save();

        return $row;
    }

    protected function normalizeUuid(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Accept only UUID-shaped values; ignore labels like "actor-1"
        if (! preg_match(
            '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
            $value
        )) {
            return null;
        }

        return $value;
    }
}
