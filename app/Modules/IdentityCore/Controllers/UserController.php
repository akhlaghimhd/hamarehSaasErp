<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\CreateTenantUserRequest;
use App\Modules\IdentityCore\DTOs\CreateTenantUserDTO;
use App\Modules\IdentityCore\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * لیست کاربران عضو مستأجر جاری
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
     * جزئیات یک عضویت
     */
    public function show(string $id): JsonResponse
    {
        $tenantUser = $this->userService->getTenantUser($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'جزئیات کاربر با موفقیت دریافت شد.',
            'data'    => $tenantUser,
        ], 200);
    }

    /**
     * ایجاد / دعوت کاربر به مستأجر جاری
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
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}