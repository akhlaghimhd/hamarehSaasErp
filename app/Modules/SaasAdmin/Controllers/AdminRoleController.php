<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\AdminRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRoleController extends Controller
{
    public function __construct(
        private readonly AdminRoleService $adminRoleService
    ) {
    }

    public function index(): JsonResponse
    {
        $roles = $this->adminRoleService->list();

        return response()->json([
            'status' => 'success',
            'data'   => $roles,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $role = $this->adminRoleService->get($id);

        return response()->json([
            'status' => 'success',
            'data'   => $role->load('permissions'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;

        $role = $this->adminRoleService->create(
            $validated['code'],
            $validated['name'],
            $validated['description'] ?? null,
            $actorId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Admin role created successfully.',
            'data'    => $role,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'status'      => 'nullable|integer|in:0,1',
        ]);

        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;

        $role = $this->adminRoleService->update(
            $id,
            $validated['name'] ?? null,
            $validated['description'] ?? null,
            $validated['status'] ?? null,
            $actorId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Admin role updated successfully.',
            'data'    => $role,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;
        $this->adminRoleService->softDelete($id, $actorId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Admin role deleted successfully.',
        ]);
    }

    public function assignPermissions(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'permission_ids'   => 'required|array',
            'permission_ids.*' => 'uuid',
        ]);

        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;

        $role = $this->adminRoleService->assignPermissions(
            $id,
            $validated['permission_ids'],
            $actorId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Permissions assigned successfully.',
            'data'    => $role,
        ]);
    }
}
