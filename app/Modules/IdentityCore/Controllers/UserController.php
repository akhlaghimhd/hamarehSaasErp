<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\CreateTenantUserRequest;
use App\Modules\IdentityCore\Requests\UpdateTenantUserRequest;
use App\Modules\IdentityCore\DTOs\CreateTenantUserDTO;
use App\Modules\IdentityCore\DTOs\UpdateTenantUserDTO;
use App\Modules\IdentityCore\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * List users belonging to the current tenant.
     */
    public function index(): JsonResponse
    {
        $users = $this->userService->listTenantUsers();

        return response()->json([
            'status'  => 'success',
            'message' => 'لیست کاربران با موفقیت دریافت شد.',
            'data'    => $users,
        ], 200);
    }

    /**
     * Show one tenant membership.
     */
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
                'message' => 'Tenant user not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create / invite user into the current tenant.
     */
    public function store(CreateTenantUserRequest $request): JsonResponse
    {
        try {
            $dto = CreateTenantUserDTO::fromRequest($request->validated());
            $tenantUser = $this->userService->createTenantUser($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'کاربر با موفقیت به مستأجر اضافه شد.',
                'data'    => $tenantUser,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update tenant membership / related user fields.
     */
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
                'message' => 'Tenant user not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Soft-delete tenant membership (no physical delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->userService->softDeleteTenantUser($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'عضویت کاربر با موفقیت حذف (soft) شد.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tenant user not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
