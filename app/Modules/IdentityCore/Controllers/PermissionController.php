<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\CreatePermissionRequest;
use App\Modules\IdentityCore\Requests\UpdatePermissionRequest;
use App\Modules\IdentityCore\DTOs\CreatePermissionDTO;
use App\Modules\IdentityCore\DTOs\UpdatePermissionDTO;
use App\Modules\IdentityCore\Services\RoleService;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    public function index(): JsonResponse
    {
        $permissions = $this->roleService->listPermissions();

        return response()->json([
            'status'  => 'success',
            'message' => 'Permissions retrieved successfully.',
            'data'    => $permissions,
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $permission = $this->roleService->getPermission($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Permission retrieved successfully.',
            'data'    => $permission,
        ], 200);
    }

    public function store(CreatePermissionRequest $request): JsonResponse
    {
        $dto = CreatePermissionDTO::fromRequest($request->validated());
        $permission = $this->roleService->createPermission($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Permission created successfully.',
            'data'    => $permission,
        ], 201);
    }

    public function update(UpdatePermissionRequest $request, string $id): JsonResponse
    {
        $dto = UpdatePermissionDTO::fromRequest($id, $request->validated());
        $permission = $this->roleService->updatePermission($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Permission updated successfully.',
            'data'    => $permission,
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->roleService->softDeletePermission($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Permission soft-deleted successfully.',
        ], 200);
    }
}
