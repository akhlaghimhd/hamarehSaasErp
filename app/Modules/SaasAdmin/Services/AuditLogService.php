<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AuditLogService
{
    public function write(
        string $entityName,
        string $actionType,
        ?string $entityId = null,
        ?string $tenantId = null,
        ?string $userId = null,
        ?string $adminUserId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $details = null,
        int $severity = 1,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sessionId = null,
        ?string $requestId = null,
        ?string $createdBy = null
    ): AuditLog {
        return AuditLog::create([
            'audit_log_id'  => (string) Str::uuid(),
            'tenant_id'     => $tenantId,
            'user_id'       => $userId,
            'admin_user_id' => $adminUserId,
            'session_id'    => $sessionId,
            'request_id'    => $requestId,
            'entity_name'   => $entityName,
            'entity_id'     => $entityId,
            'action_type'   => $actionType,
            'severity'     => $severity,
            'ip_address'    => $ipAddress,
            'user_agent'    => $userAgent,
            'old_values'    => $oldValues,
            'new_values'    => $newValues,
            'details'       => $details,
            'log_date'      => now(),
            'created_at'    => now(),
            'created_by'    => $createdBy,
        ]);
    }

    public function list(
        ?string $tenantId = null,
        ?string $entityName = null,
        ?string $actionType = null,
        int $limit = 100
    ): Collection {
        $q = AuditLog::query()->orderByDesc('log_date');

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }
        if ($entityName) {
            $q->where('entity_name', $entityName);
        }
        if ($actionType) {
            $q->where('action_type', $actionType);
        }

        return $q->limit($limit)->get();
    }
}
