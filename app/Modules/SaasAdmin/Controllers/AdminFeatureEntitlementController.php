<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\AuditLogService;
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
        private readonly FeatureCatalogService $features,
        private readonly AuditLogService $auditLogService
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
                'tenant_id'     => $tenantId,
                'enabled_codes' => $this->features->enabledCodesForTenant($tenantId),
                'entitlements'  => $this->features->listEntitlements($tenantId),
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

        $admin = $request->attributes->get('admin_user');
        $adminId = $admin?->admin_user_id;
        $session = $request->attributes->get('admin_session');

        $beforeEnabled = $this->features->isEnabled($tenantId, $data['feature_code']);

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

        $action = $data['is_enabled'] ? 'GRANT' : 'REVOKE';

        $this->auditLogService->write(
            entityName: 'tenant_feature_entitlements',
            actionType: $action,
            entityId: is_object($row) ? ($row->tenant_feature_entitlement_id ?? null) : null,
            tenantId: $tenantId,
            adminUserId: $adminId,
            oldValues: ['is_enabled' => $beforeEnabled],
            newValues: [
                'feature_code' => $data['feature_code'],
                'is_enabled'   => (bool) $data['is_enabled'],
                'notes'        => $data['notes'] ?? null,
            ],
            details: [
                'source' => 'MANUAL',
            ],
            severity: 2,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            sessionId: $session?->session_id,
            createdBy: $adminId
        );

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
