<?php

namespace App\Modules\SaasPlatform\Services;

use App\Modules\SaasPlatform\Events\TenantFeatureGrantedV1;
use App\Modules\SaasPlatform\Events\TenantFeatureRevokedV1;
use App\Modules\SaasPlatform\Models\PlatformFeatureCatalog;
use App\Modules\SaasPlatform\Models\TenantFeatureEntitlement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * PLT-W1-01 / PLT-W1-03 / SAASADM-P0 — Single source of truth for tenant feature packs.
 *
 * Freeze (downgrade): set is_enabled=false — existing operational data remains;
 * create paths call assertEnabled and reject new multi-* entities.
 *
 * Outbox: saas.tenant_feature.granted.v1 / revoked.v1 on enable/disable transitions.
 */
class FeatureCatalogService
{
    public const CACHE_TTL_SECONDS = 120;

    /** Known independent org packs (product law). */
    public const CODE_MULTI_COMPANY = 'multi_company';
    public const CODE_MULTI_BRANCH = 'multi_branch';
    public const CODE_MULTI_BUSINESS_UNIT = 'multi_business_unit';
    public const CODE_CUSTOM_ORG_HIERARCHY = 'custom_org_hierarchy';
    public const CODE_ORG_INTERCOMPANY = 'org.intercompany';

    /** Sales / Purch structure packs (DEBT-ORG-003 / H3) — independent sellable flags. */
    public const CODE_ORG_SALES_STRUCTURE = 'org.sales_structure';
    public const CODE_ORG_PURCH_STRUCTURE = 'org.purch_structure';

    public function listCatalog(bool $activeOnly = true): Collection
    {
        $q = PlatformFeatureCatalog::query()->orderBy('sort_order')->orderBy('code');
        if ($activeOnly) {
            $q->where('is_active', true);
        }

        return $q->get();
    }

    /**
     * Whether the tenant currently has the pack enabled.
     * Missing entitlement = disabled (must purchase).
     */
    public function isEnabled(string $tenantId, string $featureCode): bool
    {
        $enabled = $this->enabledCodesForTenant($tenantId);

        return in_array($featureCode, $enabled, true);
    }

    /**
     * @return list<string>
     */
    public function enabledCodesForTenant(string $tenantId): array
    {
        $cacheKey = $this->cacheKey($tenantId);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenantId) {
            $now = now();

            return TenantFeatureEntitlement::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_enabled', true)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($now) {
                    $q->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>', $now);
                })
                ->pluck('feature_code')
                ->unique()
                ->values()
                ->all();
        });
    }

    public function listEntitlements(string $tenantId): Collection
    {
        return TenantFeatureEntitlement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('feature_code')
            ->get();
    }

    /**
     * Enable or update an entitlement (SaaS Admin / internal).
     * Does not hard-delete on disable — sets is_enabled=false (PLT-W1-03 freeze semantics).
     */
    public function setEntitlement(
        string $tenantId,
        string $featureCode,
        bool $enabled,
        string $source = TenantFeatureEntitlement::SOURCE_MANUAL,
        ?string $notes = null
    ): TenantFeatureEntitlement {
        $featureCode = trim($featureCode);
        if ($featureCode === '') {
            throw new HttpException(422, 'feature_code الزامی است.');
        }

        $catalog = PlatformFeatureCatalog::query()
            ->where('code', $featureCode)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$catalog) {
            throw new HttpException(422, "بسته ویژگی «{$featureCode}» در کاتالوگ یافت نشد یا غیرفعال است.");
        }

        return DB::transaction(function () use ($tenantId, $featureCode, $enabled, $source, $notes) {
            $row = TenantFeatureEntitlement::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('feature_code', $featureCode)
                ->whereNull('deleted_at')
                ->first();

            $previousEnabled = $row ? (bool) $row->is_enabled : null;

            if ($row) {
                $row->is_enabled = $enabled;
                $row->source = $source;
                if ($notes !== null) {
                    $row->notes = $notes;
                }
                $row->row_version = ((int) ($row->row_version ?? 1)) + 1;
                $row->save();
            } else {
                $row = TenantFeatureEntitlement::create([
                    'entitlement_id' => (string) Str::uuid(),
                    'tenant_id'      => $tenantId,
                    'feature_code'   => $featureCode,
                    'is_enabled'     => $enabled,
                    'source'         => $source,
                    'notes'          => $notes,
                ]);
            }

            $this->forgetCache($tenantId);

            if ($previousEnabled !== $enabled) {
                if ($enabled) {
                    $this->logEventOutbox(
                        $tenantId,
                        TenantFeatureGrantedV1::AGGREGATE_TYPE,
                        (string) $row->entitlement_id,
                        TenantFeatureGrantedV1::EVENT_TYPE,
                        TenantFeatureGrantedV1::payload(
                            $tenantId,
                            $featureCode,
                            (string) $row->entitlement_id,
                            $source,
                            $notes
                        )
                    );
                } else {
                    $this->logEventOutbox(
                        $tenantId,
                        TenantFeatureRevokedV1::AGGREGATE_TYPE,
                        (string) $row->entitlement_id,
                        TenantFeatureRevokedV1::EVENT_TYPE,
                        TenantFeatureRevokedV1::payload(
                            $tenantId,
                            $featureCode,
                            (string) $row->entitlement_id,
                            $source,
                            $notes
                        )
                    );
                }
            }

            return $row;
        });
    }

    public function freezeEntitlement(
        string $tenantId,
        string $featureCode,
        ?string $notes = null
    ): TenantFeatureEntitlement {
        return $this->setEntitlement(
            $tenantId,
            $featureCode,
            false,
            TenantFeatureEntitlement::SOURCE_MANUAL,
            $notes ?? 'Frozen (pack downgrade) — existing data retained; new creates blocked.'
        );
    }

    public function unfreezeEntitlement(
        string $tenantId,
        string $featureCode,
        ?string $notes = null
    ): TenantFeatureEntitlement {
        return $this->setEntitlement(
            $tenantId,
            $featureCode,
            true,
            TenantFeatureEntitlement::SOURCE_MANUAL,
            $notes ?? 'Unfrozen (pack upgrade / re-enable).'
        );
    }

    public function assertEnabled(string $tenantId, string $featureCode): void
    {
        if (!$this->isEnabled($tenantId, $featureCode)) {
            throw new HttpException(
                403,
                "بسته ویژگی «{$featureCode}» برای این سازمان فعال نیست. از پنل SaaS Admin خریداری یا فعال‌سازی کنید."
            );
        }
    }

    public function forgetCache(string $tenantId): void
    {
        Cache::forget($this->cacheKey($tenantId));
    }

    private function cacheKey(string $tenantId): string
    {
        return 'feature_entitlements:'.$tenantId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => (string) Str::uuid(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
