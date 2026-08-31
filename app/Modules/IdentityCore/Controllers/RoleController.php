<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\CreateRoleRequest;
use App\Modules\IdentityCore\Requests\UpdateRoleRequest;
use App\Modules\IdentityCore\Requests\AssignRoleRequest;
use App\Modules\IdentityCore\Requests\AssignPermissionsRequest;
use App\Modules\IdentityCore\DTOs\CreateRoleDTO;
use App\Modules\IdentityCore\DTOs\UpdateRoleDTO;
use App\Modules\IdentityCore\DTOs\AssignRoleToUserDTO;
use App\Modules\IdentityCore\DTOs\AssignPermissionsToRoleDTO;
use App\Modules\IdentityCore\Services\RoleService;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    public function index(): JsonResponse
    {
        $roles = $this->roleService->listRoles();

        return response()->json([
            'status'  => 'success',
            'message' => 'Roles retrieved successfully.',
            'data'    => $roles,
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $role = $this->roleService->getRole($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Role retrieved successfully.',
            'data'    => $role,
        ], 200);
    }

    public function store(CreateRoleRequest $request): JsonResponse
    {
        $dto = CreateRoleDTO::fromRequest($request->validated());
        $role = $this->roleService->createRole($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Role created successfully.',
            'data'    => $role,
        ], 201);
    }

    public function update(UpdateRoleRequest $request, string $id): JsonResponse
    {
        $dto = UpdateRoleDTO::fromRequest($id, $request->validated());
        $role = $this->roleService->updateRole($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Role updated successfully.',
            'data'    => $role,
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->roleService->softDeleteRole($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Role soft-deleted successfully.',
        ], 200);
    }

    public function assign(AssignRoleRequest $request): JsonResponse
    {
        $dto = AssignRoleToUserDTO::fromRequest($request->validated());
        $this->roleService->assignRoleToUser($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Role assigned to user successfully.',
        ], 200);
    }

    public function assignPermissions(AssignPermissionsRequest $request): JsonResponse
    {
        $dto = AssignPermissionsToRoleDTO::fromRequest($request->validated());
        $this->roleService->assignPermissionsToRole($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Permissions assigned to role successfully.',
        ], 200);
    }
}
