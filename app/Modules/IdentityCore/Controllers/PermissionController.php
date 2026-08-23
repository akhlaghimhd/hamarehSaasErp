<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\CreatePermissionRequest;
use App\Modules\IdentityCore\DTOs\CreatePermissionDTO;
use App\Modules\IdentityCore\Services\RoleService;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    /**
     * لیست مجوزهای مستأجر جاری
     */
    public function index(): JsonResponse
    {
        $permissions = $this->roleService->listPermissions();

        return response()->json([
            'status'  => 'success',
            'message' => 'لیست مجوزها با موفقیت دریافت شد.',
            'data'    => $permissions,
        ], 200);
    }

    /**
     * ایجاد مجوز جدید برای مستأجر جاری
     */
    public function store(CreatePermissionRequest $request): JsonResponse
    {
        $dto = CreatePermissionDTO::fromRequest($request->validated());
        $permission = $this->roleService->createPermission($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'مجوز با موفقیت ایجاد شد.',
            'data'    => $permission,
        ], 201);
    }
}