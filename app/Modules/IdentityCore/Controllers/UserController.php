<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\CreateTenantUserRequest;
use App\Modules\IdentityCore\Requests\UpdateTenantUserRequest;
use App\Modules\IdentityCore\DTOs\CreateTenantUserDTO;
use App\Modules\IdentityCore\DTOs\UpdateTenantUserDTO;
use App\Modules\IdentityCore\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * List users belonging to the current tenant.
     * Query: membership=active|deleted (default active).
     */
    public function index(Request $request): JsonResponse
    {
        $filter = $request->query('membership', 'active');
        if (!in_array($filter, ['active', 'deleted'], true)) {
            $filter = 'active';
        }

        $users = $this->userService->listTenantUsers($filter);

        return response()->json([
            'status'  => 'success',
            'message' => 'لیست کاربران با موفقیت دریافت شد.',
            'data'    => $users,
        ], 200);
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

    /**
     * Restore soft-deleted membership (permission: identity.user.restore).
     */
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
