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
     * Optional query: tenant_user_id, limit, reason_code (JOINER|MOVER|LEAVER|...).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantUserId = $request->query('tenant_user_id');
            $limit = (int) $request->query('limit', 100);
            $reasonCode = $request->query('reason_code');

            $rows = $this->membershipHistoryService->listForTenant(
                $tenantUserId ? (string) $tenantUserId : null,
                $limit,
                $reasonCode ? (string) $reasonCode : null
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'تاریخچه عضویت با موفقیت دریافت شد.',
                'data'    => $rows,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function byTenantUser(string $tenantUserId): JsonResponse
    {
        try {
            $rows = $this->membershipHistoryService->listByTenantUser($tenantUserId);

            return response()->json([
                'status'  => 'success',
                'message' => 'تاریخچه عضویت با موفقیت دریافت شد.',
                'data'    => $rows,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'عضویت مستأجر یافت نشد.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
