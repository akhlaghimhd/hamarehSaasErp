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

        $row = new SmartActionLog();
        $row->forceFill([
            'smart_action_log_id' => (string) Str::uuid(),
            'tenant_id'           => $tenantId,
            'action_type'         => $actionType,
            'feature_code'        => $featureCode,
            'actor_id'            => $actorId,
            'decision'            => $decision,
            'payload'             => $payload,
            'related_entity_type' => $relatedType,
            'related_entity_id'   => $relatedId,
            'created_at'          => now(),
        ]);
        $row->save();

        return $row;
    }
}
