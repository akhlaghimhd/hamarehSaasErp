<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\CreateRoleRequest;
use App\Modules\IdentityCore\Requests\AssignRoleRequest;
use App\Modules\IdentityCore\Requests\AssignPermissionsRequest;
use App\Modules\IdentityCore\DTOs\CreateRoleDTO;
use App\Modules\IdentityCore\DTOs\AssignRoleToUserDTO;
use App\Modules\IdentityCore\DTOs\AssignPermissionsToRoleDTO;
use App\Modules\IdentityCore\Services\RoleService;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    /**
     * لیست نقش‌های مستأجر جاری (با فیلتر خودکار TenantScoped)
     */
    public function index(): JsonResponse
    {
        $roles = $this->roleService->listRoles();

        return response()->json([
            'status'  => 'success',
            'message' => 'لیست نقش‌ها با موفقیت دریافت شد.',
            'data'    => $roles,
        ], 200);
    }

    public function store(CreateRoleRequest $request): JsonResponse
    {
        $dto = CreateRoleDTO::fromRequest($request->validated());
        $role = $this->roleService->createRole($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'نقش با موفقیت ایجاد شد.',
            'data'    => $role,
        ], 201);
    }

    public function assign(AssignRoleRequest $request): JsonResponse
    {
        $dto = AssignRoleToUserDTO::fromRequest($request->validated());
        $this->roleService->assignRoleToUser($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'نقش‌های مورد نظر با موفقیت به کاربر تخصیص داده شدند.',
        ], 200);
    }

    public function assignPermissions(AssignPermissionsRequest $request): JsonResponse
    {
        $dto = AssignPermissionsToRoleDTO::fromRequest($request->validated());
        $this->roleService->assignPermissionsToRole($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'مجوزها با موفقیت به نقش تخصیص داده شدند.',
        ], 200);
    }
}