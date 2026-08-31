<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Services\MembershipHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class MembershipHistoryController extends Controller
{
    public function __construct(
        private readonly MembershipHistoryService $membershipHistoryService
    ) {}

    /**
     * List membership history for the current tenant.
     * Optional query: tenant_user_id, limit.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantUserId = $request->query('tenant_user_id');
            $limit = (int) $request->query('limit', 100);

            $rows = $this->membershipHistoryService->listForTenant(
                $tenantUserId ? (string) $tenantUserId : null,
                $limit
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Membership history retrieved successfully.',
                'data'    => $rows,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * List history for one tenant membership.
     */
    public function byTenantUser(string $tenantUserId): JsonResponse
    {
        try {
            $rows = $this->membershipHistoryService->listByTenantUser($tenantUserId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Membership history retrieved successfully.',
                'data'    => $rows,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tenant membership not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
