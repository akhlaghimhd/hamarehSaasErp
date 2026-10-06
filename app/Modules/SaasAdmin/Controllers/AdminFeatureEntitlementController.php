<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Platform-admin only — manage feature packs for any tenant (no tenant JWT context).
 */
class AdminFeatureEntitlementController extends Controller
{
    public function __construct(
        private readonly FeatureCatalogService $features
    ) {
    }

    public function catalog(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->features->listCatalog(true),
        ]);
    }

    public function listForTenant(string $tenantId): JsonResponse
    {
        $this->assertTenantExists($tenantId);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'tenant_id'      => $tenantId,
                'enabled_codes'  => $this->features->enabledCodesForTenant($tenantId),
                'entitlements'   => $this->features->listEntitlements($tenantId),
            ],
        ]);
    }

    public function setForTenant(Request $request, string $tenantId): JsonResponse
    {
        $this->assertTenantExists($tenantId);

        $data = $request->validate([
            'feature_code' => 'required|string|max:100',
            'is_enabled'   => 'required|boolean',
            'notes'        => 'nullable|string|max:500',
        ]);

        try {
            $row = $this->features->setEntitlement(
                $tenantId,
                $data['feature_code'],
                (bool) $data['is_enabled'],
                'MANUAL',
                $data['notes'] ?? null
            );
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        return response()->json([
            'status'  => 'success',
            'message' => $data['is_enabled'] ? 'Feature pack granted.' : 'Feature pack revoked.',
            'data'    => $row,
        ]);
    }

    private function assertTenantExists(string $tenantId): void
    {
        $exists = DB::table('tenants')->where('tenant_id', $tenantId)->exists();
        if (! $exists) {
            abort(404, 'مستأجر یافت نشد.');
        }
    }
}
