<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerActivityLog;
use App\Modules\PartnerLayer\DTOs\CreatePartnerActivityLogDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;

/**
 * P3-S8 — Append-only partner activity log.
 * user_id is logical reference to IdentityCore. No update/delete of business meaning.
 */
class PartnerActivityLogService
{
    public function getLogs(?string $partnerId = null): Collection
    {
        $query = PartnerActivityLog::query()->orderBy('created_at', 'desc');

        if ($partnerId) {
            $this->assertPartnerAccessible($partnerId);
            $query->where('partner_id', $partnerId);
        } else {
            $ids = $this->accessiblePartnerIds();
            if ($ids->isEmpty()) {
                return collect();
            }
            $query->whereIn('partner_id', $ids);
        }

        return $query->get();
    }

    public function getLogById(string $logId): PartnerActivityLog
    {
        $log = PartnerActivityLog::query()
            ->where('partner_log_id', $logId)
            ->firstOrFail();

        $this->assertPartnerAccessible($log->partner_id);

        return $log;
    }

    public function createLog(CreatePartnerActivityLogDTO $dto): PartnerActivityLog
    {
        $this->assertPartnerAccessible($dto->partnerId);

        return PartnerActivityLog::create([
            'partner_id'  => $dto->partnerId,
            'user_id'     => $dto->userId,
            'action_type' => $dto->actionType,
            'description' => $dto->description,
            'ip_address'  => $dto->ipAddress,
            'created_at'  => now(),
        ]);
    }

    private function assertPartnerAccessible(string $partnerId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $query = Partner::query()->where('partner_id', $partnerId);

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        if (!$query->exists()) {
            throw new Exception('Partner not found or not accessible in this context.');
        }
    }

    private function accessiblePartnerIds(): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $query = Partner::query()->select('partner_id');

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        return $query->pluck('partner_id');
    }
}
