<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Platform-admin read model over L1 tenants (SAASADM-P5).
 * Does not replace TenantService create path; ops list/show only.
 */
class AdminTenantService
{
    public function __construct(
        private readonly FeatureCatalogService $features
    ) {
    }

    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function list(
        ?string $search = null,
        ?int $status = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $perPage = max(1, min(100, $perPage));

        $q = Tenant::query()
            ->whereNull('deleted_at')
            ->orderByDesc('created_at');

        if ($status !== null) {
            $q->where('status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($search)).'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('tenant_name', 'ilike', $term)
                    ->orWhere('tenant_code', 'ilike', $term)
                    ->orWhere('slug', 'ilike', $term)
                    ->orWhere('legal_name', 'ilike', $term);
            });
        }

        return $q->paginate($perPage);
    }

    /**
     * @return array{
     *   tenant: Tenant,
     *   enabled_codes: list<string>,
     *   entitlements: mixed,
     *   member_count: int
     * }
     */
    public function show(string $tenantId): array
    {
        $tenant = Tenant::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $memberCount = (int) DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->count();

        return [
            'tenant'         => $tenant,
            'enabled_codes'  => $this->features->enabledCodesForTenant($tenantId),
            'entitlements'   => $this->features->listEntitlements($tenantId),
            'member_count'   => $memberCount,
        ];
    }
}
