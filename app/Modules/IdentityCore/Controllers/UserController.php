<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\CreateTenantUserRequest;
use App\Modules\IdentityCore\Requests\UpdateTenantUserRequest;
use App\Modules\IdentityCore\DTOs\CreateTenantUserDTO;
use App\Modules\IdentityCore\DTOs\UpdateTenantUserDTO;
use App\Modules\IdentityCore\Services\UserService;
use App\Modules\IdentityCore\Services\TenantUserSearchService;
use App\Modules\IdentityCore\Services\OrganizationalEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly TenantUserSearchService $tenantUserSearchService,
        private readonly OrganizationalEmailService $organizationalEmailService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filter = $request->query('membership', 'active');
        if (!in_array($filter, ['active', 'deleted'], true)) {
            $filter = 'active';
        }

        $search = $request->query('q');
        $search = is_string($search) ? $search : null;

        $limitRaw = $request->query('limit');
        $limit = is_numeric($limitRaw) ? (int) $limitRaw : null;
        if ($limit !== null && $limit < 1) {
            $limit = null;
        }

        $companyId = $request->query('company_id');
        $companyId = is_string($companyId) && trim($companyId) !== '' ? trim($companyId) : null;

        if (is_string($search) && mb_strlen(trim($search)) >= 2) {
            $users = $this->tenantUserSearchService->search(
                trim($search),
                $limit ?? 25,
                $filter
            );
        } else {
            $users = $this->userService->listTenantUsers($filter);
        }

        // ADR-ID-ORG-003 H3: optional per-company filter (single query, not N+1)
        if ($companyId !== null) {
            $matchingIds = \Illuminate\Support\Facades\DB::table('tenant_user_scopes')
                ->join('tenant_scopes', 'tenant_user_scopes.scope_id', '=', 'tenant_scopes.scope_id')
                ->whereNull('tenant_user_scopes.deleted_at')
                ->whereNull('tenant_scopes.deleted_at')
                ->where('tenant_scopes.is_active', true)
                ->whereRaw('UPPER(tenant_scopes.scope_type) = ?', ['COMPANY'])
                ->where('tenant_scopes.reference_id', $companyId)
                ->pluck('tenant_user_scopes.tenant_user_id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $matchingSet = array_flip($matchingIds);
            $users = collect($users)->filter(function ($tu) use ($matchingSet) {
                $tenantUserId = (string) (is_array($tu) ? ($tu['tenant_user_id'] ?? '') : ($tu->tenant_user_id ?? ''));
                return $tenantUserId !== '' && isset($matchingSet[$tenantUserId]);
            })->values();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'لیست کاربران با موفقیت دریافت شد.',
            'data'    => $users,
        ], 200);
    }

    /**
     * Email host for current tenant (for member-create UI).
     */
    public function emailHost(): JsonResponse
    {
        try {
            $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
            if (!$tenantId) {
                throw new Exception('Tenant Context is missing. Architecture Violation.');
            }

            $host = $this->organizationalEmailService->resolveEmailHost((string) $tenantId);

            return response()->json([
                'status'  => 'success',
                'message' => 'دامنه ایمیل سازمانی دریافت شد.',
                'data'    => [
                    'email_host' => $host,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $tenantUser = $this->userService->getTenantUser($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'جزئیات کاربر با موفقیت دریافت شد.',
                'data'    => $tenantUser,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'کاربر سازمان یافت نشد.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function store(CreateTenantUserRequest $request): JsonResponse
    {
        try {
            $dto = CreateTenantUserDTO::fromRequest($request->validated());
            $tenantUser = $this->userService->createTenantUser($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'کاربر با موفقیت به سازمان اضافه شد.',
                'data'    => $tenantUser,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(UpdateTenantUserRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateTenantUserDTO::fromRequest($id, $request->validated());
            $tenantUser = $this->userService->updateTenantUser($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'کاربر با موفقیت به‌روزرسانی شد.',
                'data'    => $tenantUser,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'کاربر سازمان یافت نشد.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->userService->softDeleteTenantUser($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'عضویت کاربر از سازمان حذف شد (قابل بازگردانی).',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'کاربر سازمان یافت نشد.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function restore(string $id): JsonResponse
    {
        try {
            $tenantUser = $this->userService->restoreTenantUser($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'عضویت کاربر بازگردانی شد. برای استفاده باید دوباره فعال شود.',
                'data'    => $tenantUser,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'کاربر حذف‌شده یافت نشد.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
