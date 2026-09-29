<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\TenantAccessCertCampaign;
use App\Modules\IdentityCore\Models\TenantAccessCertItem;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W2-01 — Access Certification campaigns (foundation).
 *
 * Lifecycle: DRAFT → open (snapshot members) → certify items → COMPLETED.
 * REVOKE_REQUESTED records intent; actual role strip is a follow-up action (not auto).
 */
class AccessCertificationService
{
    public function listCampaigns(string $tenantId): Collection
    {
        return TenantAccessCertCampaign::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getCampaign(string $tenantId, string $campaignId): TenantAccessCertCampaign
    {
        $campaign = TenantAccessCertCampaign::query()
            ->where('tenant_id', $tenantId)
            ->where('campaign_id', $campaignId)
            ->whereNull('deleted_at')
            ->first();

        if (!$campaign) {
            throw new HttpException(404, 'کمپین گواهی دسترسی یافت نشد.');
        }

        return $campaign;
    }

    public function createCampaign(
        string $tenantId,
        string $code,
        string $name,
        ?string $description = null,
        ?string $dueAt = null,
        ?string $ownerUserId = null,
        ?string $actorId = null
    ): TenantAccessCertCampaign {
        $code = strtolower(trim($code));
        if ($code === '' || !preg_match('/^[a-z0-9_\-]{2,80}$/', $code)) {
            throw new HttpException(422, 'کد کمپین نامعتبر است.');
        }

        $exists = TenantAccessCertCampaign::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw new HttpException(422, 'کد کمپین تکراری است.');
        }

        return TenantAccessCertCampaign::create([
            'campaign_id'   => (string) Str::uuid(),
            'tenant_id'     => $tenantId,
            'code'          => $code,
            'name'          => $name,
            'description'   => $description,
            'status'        => TenantAccessCertCampaign::STATUS_DRAFT,
            'due_at'        => $dueAt,
            'owner_user_id' => $ownerUserId,
            'created_by'    => $actorId,
            'row_version'   => 1,
        ]);
    }

    /**
     * Open campaign and generate one item per active membership.
     */
    public function openCampaign(string $tenantId, string $campaignId, ?string $actorId = null): TenantAccessCertCampaign
    {
        return DB::transaction(function () use ($tenantId, $campaignId, $actorId) {
            $campaign = $this->getCampaign($tenantId, $campaignId);

            if ($campaign->status !== TenantAccessCertCampaign::STATUS_DRAFT) {
                throw new HttpException(422, 'فقط کمپین در وضعیت DRAFT قابل باز شدن است.');
            }

            $members = TenantUser::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->get(['tenant_user_id', 'user_id']);

            foreach ($members as $member) {
                $roleIds = DB::table('tenant_user_roles')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $member->user_id)
                    ->pluck('tenant_role_id')
                    ->values()
                    ->all();

                TenantAccessCertItem::create([
                    'item_id'           => (string) Str::uuid(),
                    'tenant_id'         => $tenantId,
                    'campaign_id'       => $campaign->campaign_id,
                    'tenant_user_id'    => $member->tenant_user_id,
                    'user_id'           => $member->user_id,
                    'role_ids_snapshot' => $roleIds,
                    'decision'          => TenantAccessCertItem::DECISION_PENDING,
                    'created_by'        => $actorId,
                    'row_version'       => 1,
                ]);
            }

            $campaign->status = TenantAccessCertCampaign::STATUS_OPEN;
            $campaign->opened_at = now();
            $campaign->updated_by = $actorId;
            $campaign->row_version = ((int) $campaign->row_version) + 1;
            $campaign->save();

            return $campaign->fresh();
        });
    }

    public function listItems(string $tenantId, string $campaignId, ?string $decision = null): Collection
    {
        $this->getCampaign($tenantId, $campaignId);

        $q = TenantAccessCertItem::query()
            ->where('tenant_id', $tenantId)
            ->where('campaign_id', $campaignId)
            ->whereNull('deleted_at')
            ->orderBy('created_at');

        if ($decision) {
            $q->where('decision', strtoupper($decision));
        }

        return $q->get();
    }

    public function certifyItem(
        string $tenantId,
        string $itemId,
        string $decision,
        string $reviewerUserId,
        ?string $note = null
    ): TenantAccessCertItem {
        $decision = strtoupper(trim($decision));
        $allowed = [
            TenantAccessCertItem::DECISION_APPROVED,
            TenantAccessCertItem::DECISION_REVOKE_REQUESTED,
            TenantAccessCertItem::DECISION_DEFERRED,
        ];
        if (!in_array($decision, $allowed, true)) {
            throw new HttpException(422, 'decision باید APPROVED، REVOKE_REQUESTED یا DEFERRED باشد.');
        }

        return DB::transaction(function () use ($tenantId, $itemId, $decision, $reviewerUserId, $note) {
            $item = TenantAccessCertItem::query()
                ->where('tenant_id', $tenantId)
                ->where('item_id', $itemId)
                ->whereNull('deleted_at')
                ->first();

            if (!$item) {
                throw new HttpException(404, 'آیتم گواهی یافت نشد.');
            }

            $campaign = $this->getCampaign($tenantId, $item->campaign_id);
            if ($campaign->status !== TenantAccessCertCampaign::STATUS_OPEN) {
                throw new HttpException(422, 'فقط کمپین باز قابل تصمیم‌گیری است.');
            }

            if ($item->decision !== TenantAccessCertItem::DECISION_PENDING && $item->decision !== TenantAccessCertItem::DECISION_DEFERRED) {
                throw new HttpException(422, 'این آیتم قبلاً تصمیم‌گیری شده است.');
            }

            $item->decision = $decision;
            $item->reviewer_user_id = $reviewerUserId;
            $item->decided_at = now();
            $item->decision_note = $note;
            $item->row_version = ((int) $item->row_version) + 1;
            $item->save();

            return $item->fresh();
        });
    }

    public function completeCampaign(string $tenantId, string $campaignId, ?string $actorId = null): TenantAccessCertCampaign
    {
        return DB::transaction(function () use ($tenantId, $campaignId, $actorId) {
            $campaign = $this->getCampaign($tenantId, $campaignId);

            if ($campaign->status !== TenantAccessCertCampaign::STATUS_OPEN) {
                throw new HttpException(422, 'فقط کمپین OPEN قابل تکمیل است.');
            }

            $pending = TenantAccessCertItem::query()
                ->where('tenant_id', $tenantId)
                ->where('campaign_id', $campaignId)
                ->where('decision', TenantAccessCertItem::DECISION_PENDING)
                ->whereNull('deleted_at')
                ->count();

            if ($pending > 0) {
                throw new HttpException(422, "هنوز {$pending} آیتم در وضعیت PENDING است.");
            }

            $campaign->status = TenantAccessCertCampaign::STATUS_COMPLETED;
            $campaign->completed_at = now();
            $campaign->updated_by = $actorId;
            $campaign->row_version = ((int) $campaign->row_version) + 1;
            $campaign->save();

            return $campaign->fresh();
        });
    }

    public function campaignSummary(string $tenantId, string $campaignId): array
    {
        $campaign = $this->getCampaign($tenantId, $campaignId);
        $items = TenantAccessCertItem::query()
            ->where('tenant_id', $tenantId)
            ->where('campaign_id', $campaignId)
            ->whereNull('deleted_at')
            ->get();

        return [
            'campaign' => $campaign,
            'totals'   => [
                'items'            => $items->count(),
                'pending'          => $items->where('decision', TenantAccessCertItem::DECISION_PENDING)->count(),
                'approved'         => $items->where('decision', TenantAccessCertItem::DECISION_APPROVED)->count(),
                'revoke_requested' => $items->where('decision', TenantAccessCertItem::DECISION_REVOKE_REQUESTED)->count(),
                'deferred'         => $items->where('decision', TenantAccessCertItem::DECISION_DEFERRED)->count(),
            ],
        ];
    }
}
