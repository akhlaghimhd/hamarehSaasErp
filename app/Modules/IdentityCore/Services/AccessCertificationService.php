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
 * Access Certification — gap-focused remediation.
 * open: only SoD issues; reEvaluate: refresh after role fixes.
 * Resolved gaps are KEPT (decision=RESOLVED) so reports remain a full audit trail.
 * Archive = soft-delete of COMPLETED campaigns (list stays light).
 */
class AccessCertificationService
{
    /**
     * @param  string  $scope  active|archived|all
     */
    public function listCampaigns(string $tenantId, string $scope = 'active'): Collection
    {
        $scope = strtolower(trim($scope));
        if (! in_array($scope, ['active', 'archived', 'all'], true)) {
            $scope = 'active';
        }

        $q = TenantAccessCertCampaign::query()->where('tenant_id', $tenantId);

        if ($scope === 'archived') {
            $q->onlyTrashed();
        } elseif ($scope === 'all') {
            $q->withTrashed();
        } else {
            $q->whereNull('deleted_at');
        }

        return $q->orderByDesc('created_at')->get()->map(function (TenantAccessCertCampaign $c) {
            $c->setAttribute('is_archived', $c->trashed());

            return $c;
        });
    }

    public function getCampaign(string $tenantId, string $campaignId, bool $withTrashed = false): TenantAccessCertCampaign
    {
        $q = TenantAccessCertCampaign::query()
            ->where('tenant_id', $tenantId)
            ->where('campaign_id', $campaignId);

        if ($withTrashed) {
            $q->withTrashed();
        } else {
            $q->whereNull('deleted_at');
        }

        $campaign = $q->first();

        if (! $campaign) {
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
        $code = $this->normalizeCampaignCode($code);

        if ($code === '') {
            $code = 'ac-'.now()->format('Ymd').'-'.Str::lower(Str::random(4));
        }

        if (! preg_match('/^[a-z0-9_\-]{2,80}$/', $code)) {
            throw new HttpException(
                422,
                'کد کمپین نامعتبر است. فقط حروف انگلیسی، عدد، خط تیره (-) و زیرخط (_) بین ۲ تا ۸۰ کاراکتر مجاز است.'
            );
        }

        $exists = TenantAccessCertCampaign::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw new HttpException(422, 'کد کمپین تکراری است. یک کد دیگر وارد کنید.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new HttpException(422, 'نام کمپین الزامی است.');
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

    private function normalizeCampaignCode(string $code): string
    {
        $code = trim($code);
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $code = str_replace($fa, $en, $code);
        $code = strtolower($code);
        $code = preg_replace('/\s+/', '-', $code) ?? $code;
        $code = preg_replace('/[^a-z0-9_\-]/', '', $code) ?? $code;
        $code = preg_replace('/-+/', '-', $code) ?? $code;
        $code = trim($code, '-_');

        return $code;
    }

    /** بایگانی کمپین پایان‌یافته (soft-delete) */
    public function archiveCampaign(string $tenantId, string $campaignId, ?string $actorId = null): TenantAccessCertCampaign
    {
        return DB::transaction(function () use ($tenantId, $campaignId, $actorId) {
            $campaign = $this->getCampaign($tenantId, $campaignId);

            if ($campaign->status !== TenantAccessCertCampaign::STATUS_COMPLETED) {
                throw new HttpException(422, 'فقط کمپین پایان‌یافته را می‌توان بایگانی کرد.');
            }

            $campaign->updated_by = $actorId;
            $campaign->deleted_by = $actorId;
            $campaign->row_version = ((int) $campaign->row_version) + 1;
            $campaign->save();
            $campaign->delete();

            return $campaign->fresh([''] ) ?? $campaign;
        });
    }

    /** خروج از بایگانی */
    public function unarchiveCampaign(string $tenantId, string $campaignId, ?string $actorId = null): TenantAccessCertCampaign
    {
        return DB::transaction(function () use ($tenantId, $campaignId, $actorId) {
            $campaign = $this->getCampaign($tenantId, $campaignId, true);

            if (! $campaign->trashed()) {
                throw new HttpException(422, 'این کمپین بایگانی نیست.');
            }

            $campaign->restore();
            $campaign->deleted_by = null;
            $campaign->updated_by = $actorId;
            $campaign->row_version = ((int) $campaign->row_version) + 1;
            $campaign->save();

            return $campaign->fresh();
        });
    }

    public function openCampaign(string $tenantId, string $campaignId, ?string $actorId = null): TenantAccessCertCampaign
    {
        return DB::transaction(function () use ($tenantId, $campaignId, $actorId) {
            $campaign = $this->getCampaign($tenantId, $campaignId);

            if ($campaign->status !== TenantAccessCertCampaign::STATUS_DRAFT) {
                throw new HttpException(422, 'فقط کمپین در وضعیت پیش‌نویس قابل باز شدن است.');
            }

            foreach ($this->scanMemberIssues($tenantId) as $row) {
                TenantAccessCertItem::create([
                    'item_id'           => (string) Str::uuid(),
                    'tenant_id'         => $tenantId,
                    'campaign_id'       => $campaign->campaign_id,
                    'tenant_user_id'    => $row['tenant_user_id'],
                    'user_id'           => $row['user_id'],
                    'role_ids_snapshot' => $row['role_ids'],
                    'sod_has_block'     => $row['has_block'],
                    'sod_has_warn'      => $row['has_warn'],
                    'sod_conflicts'     => $row['conflicts'],
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

    public function reEvaluateCampaign(string $tenantId, string $campaignId, ?string $actorId = null): array
    {
        return DB::transaction(function () use ($tenantId, $campaignId, $actorId) {
            $campaign = $this->getCampaign($tenantId, $campaignId);

            if ($campaign->status !== TenantAccessCertCampaign::STATUS_OPEN) {
                throw new HttpException(422, 'فقط کمپین باز قابل بررسی مجدد است.');
            }

            $issues = $this->scanMemberIssues($tenantId);
            $issueByUser = [];
            foreach ($issues as $row) {
                $issueByUser[(string) $row['user_id']] = $row;
            }

            $existing = TenantAccessCertItem::query()
                ->where('tenant_id', $tenantId)
                ->where('campaign_id', $campaignId)
                ->whereNull('deleted_at')
                ->get();

            $resolved = 0;
            $updated = 0;
            $added = 0;
            $seenUsers = [];

            foreach ($existing as $item) {
                $uid = (string) $item->user_id;
                $seenUsers[$uid] = true;
                $decision = strtoupper((string) ($item->decision ?? 'PENDING'));

                if (! isset($issueByUser[$uid])) {
                    if (in_array($decision, [
                        TenantAccessCertItem::DECISION_APPROVED,
                        TenantAccessCertItem::DECISION_DEFERRED,
                        TenantAccessCertItem::DECISION_RESOLVED,
                    ], true)) {
                        continue;
                    }

                    $item->decision = TenantAccessCertItem::DECISION_RESOLVED;
                    $item->reviewer_user_id = $actorId ?: $item->reviewer_user_id;
                    $item->decided_at = now();
                    if (empty($item->decision_note)) {
                        $item->decision_note = 'رفع‌شده با اصلاح نقش پس از بررسی مجدد';
                    }
                    $item->row_version = ((int) $item->row_version) + 1;
                    $item->save();
                    $resolved++;
                    continue;
                }

                $row = $issueByUser[$uid];

                $item->role_ids_snapshot = $row['role_ids'];
                $item->sod_has_block = $row['has_block'];
                $item->sod_has_warn = $row['has_warn'];
                $item->sod_conflicts = $row['conflicts'];

                if ($decision === TenantAccessCertItem::DECISION_REVOKE_REQUESTED
                    || $decision === TenantAccessCertItem::DECISION_RESOLVED) {
                    $item->decision = TenantAccessCertItem::DECISION_PENDING;
                    $item->decided_at = null;
                    $item->decision_note = null;
                }

                $item->row_version = ((int) $item->row_version) + 1;
                $item->save();
                $updated++;
            }

            foreach ($issueByUser as $uid => $row) {
                if (isset($seenUsers[$uid])) {
                    continue;
                }
                TenantAccessCertItem::create([
                    'item_id'           => (string) Str::uuid(),
                    'tenant_id'         => $tenantId,
                    'campaign_id'       => $campaign->campaign_id,
                    'tenant_user_id'    => $row['tenant_user_id'],
                    'user_id'           => $row['user_id'],
                    'role_ids_snapshot' => $row['role_ids'],
                    'sod_has_block'     => $row['has_block'],
                    'sod_has_warn'      => $row['has_warn'],
                    'sod_conflicts'     => $row['conflicts'],
                    'decision'          => TenantAccessCertItem::DECISION_PENDING,
                    'created_by'        => $actorId,
                    'row_version'       => 1,
                ]);
                $added++;
            }

            $campaign->updated_by = $actorId;
            $campaign->row_version = ((int) $campaign->row_version) + 1;
            $campaign->save();

            $summary = $this->campaignSummary($tenantId, $campaignId);

            return [
                'campaign' => $summary['campaign'],
                'totals'   => $summary['totals'],
                're_eval'  => [
                    'resolved' => $resolved,
                    'updated'  => $updated,
                    'added'    => $added,
                ],
            ];
        });
    }

    public function listItems(string $tenantId, string $campaignId, ?string $decision = null): Collection
    {
        $this->getCampaign($tenantId, $campaignId, true);

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
        if (! in_array($decision, $allowed, true)) {
            throw new HttpException(422, 'تصمیم باید پذیرش استثنا، در حال اصلاح یا موکول باشد.');
        }

        return DB::transaction(function () use ($tenantId, $itemId, $decision, $reviewerUserId, $note) {
            $item = TenantAccessCertItem::query()
                ->where('tenant_id', $tenantId)
                ->where('item_id', $itemId)
                ->whereNull('deleted_at')
                ->first();

            if (! $item) {
                throw new HttpException(404, 'آیتم گواهی یافت نشد.');
            }

            $campaign = $this->getCampaign($tenantId, $item->campaign_id);
            if ($campaign->status !== TenantAccessCertCampaign::STATUS_OPEN) {
                throw new HttpException(422, 'فقط کمپین باز قابل تصمیم‌گیری است.');
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
                throw new HttpException(422, 'فقط کمپین باز قابل تکمیل است.');
            }

            $pending = TenantAccessCertItem::query()
                ->where('tenant_id', $tenantId)
                ->where('campaign_id', $campaignId)
                ->where('decision', TenantAccessCertItem::DECISION_PENDING)
                ->whereNull('deleted_at')
                ->count();

            $inProgress = TenantAccessCertItem::query()
                ->where('tenant_id', $tenantId)
                ->where('campaign_id', $campaignId)
                ->where('decision', TenantAccessCertItem::DECISION_REVOKE_REQUESTED)
                ->whereNull('deleted_at')
                ->count();

            $openWork = $pending + $inProgress;
            if ($openWork > 0) {
                throw new HttpException(
                    422,
                    "هنوز {$openWork} مورد باز باقی مانده است. یا نقش را اصلاح و «بررسی مجدد» بزنید، یا «پذیرش استثنا» / «موکول» کنید."
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
        $campaign = $this->getCampaign($tenantId, $campaignId, true);
        $items = TenantAccessCertItem::query()
            ->where('tenant_id', $tenantId)
            ->where('campaign_id', $campaignId)
            ->whereNull('deleted_at')
            ->get();

        return [
            'campaign' => $campaign,
            'totals'   => [
                'items'            => $items->count(),
                'pending'          => $items->where('decision', TenantAccessCertItem::DECISION_PENDING)->count()
                    + $items->where('decision', TenantAccessCertItem::DECISION_REVOKE_REQUESTED)->count(),
                'approved'         => $items->where('decision', TenantAccessCertItem::DECISION_APPROVED)->count(),
                'revoke_requested' => $items->where('decision', TenantAccessCertItem::DECISION_REVOKE_REQUESTED)->count(),
                'deferred'         => $items->where('decision', TenantAccessCertItem::DECISION_DEFERRED)->count(),
                'resolved'         => $items->where('decision', TenantAccessCertItem::DECISION_RESOLVED)->count(),
                'sod_block'        => $items->where('sod_has_block', true)->count(),
                'sod_warn'         => $items->where('sod_has_warn', true)->count(),
            ],
        ];
    }

    private function scanMemberIssues(string $tenantId): array
    {
        $sod = app(SodService::class);

        $members = TenantUser::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->get(['tenant_user_id', 'user_id']);

        $out = [];
        foreach ($members as $member) {
            $roleIds = DB::table('tenant_user_roles')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $member->user_id)
                ->pluck('tenant_role_id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();

            $eval = $sod->evaluateRoleSet($tenantId, $roleIds);
            $hasBlock = (bool) ($eval['has_block'] ?? false);
            $hasWarn = (bool) ($eval['has_warn'] ?? false);

            if (! $hasBlock && ! $hasWarn) {
                continue;
            }

            $out[] = [
                'tenant_user_id' => (string) $member->tenant_user_id,
                'user_id'        => (string) $member->user_id,
                'role_ids'       => $roleIds,
                'has_block'      => $hasBlock,
                'has_warn'       => $hasWarn,
                'conflicts'      => $eval['conflicts'] ?? [],
            ];
        }

        return $out;
    }
}
