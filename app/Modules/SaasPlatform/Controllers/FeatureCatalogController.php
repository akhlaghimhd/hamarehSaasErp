<?php

namespace App\Modules\SaasPlatform\Controllers;

use App\Base\Controller;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

class FeatureCatalogController extends Controller
{
    public function __construct(
        private readonly FeatureCatalogService $featureCatalog
    ) {}

    /** Global sellable catalog (read). */
    public function catalog(): JsonResponse
    {
        $items = $this->featureCatalog->listCatalog(true);

        return response()->json([
            'status'  => 'success',
            'message' => 'Feature catalog retrieved.',
            'data'    => $items,
        ], 200);
    }

    /** Current tenant entitlements + enabled codes. */
    public function myEntitlements(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $enabled = $this->featureCatalog->enabledCodesForTenant($tenantId);
        $rows = $this->featureCatalog->listEntitlements($tenantId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tenant feature entitlements retrieved.',
            'data'    => [
                'tenant_id'     => $tenantId,
                'enabled_codes' => $enabled,
                'entitlements'  => $rows,
            ],
        ], 200);
    }

    /** SaaS Admin: set entitlement for a tenant. */
    public function setEntitlement(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'tenant_id'    => 'required|uuid',
                'feature_code' => 'required|string|max:80',
                'is_enabled'   => 'required|boolean',
                'source'       => 'nullable|string|in:PLAN,ADDON,MANUAL,TRIAL',
                'notes'        => 'nullable|string|max:500',
            ]);

            $row = $this->featureCatalog->setEntitlement(
                $validated['tenant_id'],
                $validated['feature_code'],
                (bool) $validated['is_enabled'],
                $validated['source'] ?? 'MANUAL',
                $validated['notes'] ?? null
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Entitlement updated.',
                'data'    => $row,
            ], 200);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function resolveTenantId(Request $request): string
    {
        $tenantId = app()->bound('current_tenant_id') ? (string) app('current_tenant_id') : '';
        if ($tenantId === '') {
            $tenantId = (string) ($request->header('X-Tenant-ID') ?? '');
        }
        if ($tenantId === '') {
            throw new HttpException(400, 'Tenant context is required.');
        }

        return $tenantId;
    }
}
