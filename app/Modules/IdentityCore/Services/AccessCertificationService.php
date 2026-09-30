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
 * ID-W2-01 — Access Certification campaigns.
 *
 * Lifecycle: DRAFT → open (snapshot members + SoD evaluate) → certify items → COMPLETED.
 * REVOKE_REQUESTED records intent only; actual role change is done on the member page (not auto).
 * While OPEN, any item decision may be changed.
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
     * Open campaign, snapshot roles per member, and evaluate active SoD rules.
     */
    public function openCampaign(string $tenantId, string $campaignId, ?string $actorId = null): TenantAccessCertCampaign
    {
        return DB::transaction(function () use ($tenantId, $campaignId, $actorId) {
            $campaign = $this->getCampaign($tenantId, $campaignId);

            if ($campaign->status !== TenantAccessCertCampaign::STATUS_DRAFT) {
                throw new HttpException(422, 'فقط کمپین در وضعیت پیش‌نویس قابل باز شدن است.');
            }

            $sod = app(SodService::class);

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
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all();

                $eval = $sod->evaluateRoleSet($tenantId, $roleIds);

                TenantAccessCertItem::create([
                    'item_id'           => (string) Str::uuid(),
                    'tenant_id'         => $tenantId,
                    'campaign_id'       => $campaign->campaign_id,
                    'tenant_user_id'    => $member->tenant_user_id,
                    'user_id'           => $member->user_id,
                    'role_ids_snapshot' => $roleIds,
                    'sod_has_block'     => (bool) ($eval['has_block'] ?? false),
                    'sod_has_warn'      => (bool) ($eval['has_warn'] ?? false),
                    'sod_conflicts'     => $eval['conflicts'] ?? [],
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
            ->orderByDesc('sod_has_block')
            ->orderByDesc('sod_has_warn')
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
            throw new HttpException(422, 'تصمیم باید تأیید، درخواست لغو نقش یا موکول باشد.');
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

            // While campaign is OPEN, reviewer may change any prior decision
            // (APPROVED / REVOKE_REQUESTED / DEFERRED / PENDING).

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
                throw new HttpException(422, 'فقط کمپین باز قابل تکمیل است.');
            }

            $pendingItems = TenantAccessCertItem::query()
                ->where('tenant_id', $tenantId)
                ->where('campaign_id', $campaignId)
                ->where('decision', TenantAccessCertItem::DECISION_PENDING)
                ->whereNull('deleted_at')
                ->get(['item_id', 'user_id', 'tenant_user_id']);

            $pending = $pendingItems->count();

            if ($pending > 0) {
                throw new HttpException(
                    422,
                    "هنوز {$pending} عضو بدون تصمیم (در انتظار بررسی) باقی مانده است. ابتدا برای همهٔ ردیف‌ها یکی از گزینه‌های تأیید، درخواست لغو نقش یا موکول را بزنید، سپس کمپین را تکمیل کنید."
                );
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
                'approved'         => $items->where('decision', TenantAccessCertItem::DECISION_APPROVED)->count(),
                'pending'          => $items->where('decision', TenantAccessCertItem::DECISION_PENDING)->count(),
                'revoke_requested' => $items->where('decision', TenantAccessCertItem::DECISION_REVOKE_REQUESTED)->count(),
                'deferred'         => $items->where('decision', TenantAccessCertItem::DECISION_DEFERRED)->count(),
                'sod_block'        => $items->where('sod_has_block', true)->count(),
                'sod_warn'         => $items->where('sod_has_warn', true)->count(),
            ],
        ];
    }
}
