<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $adminUserService
    ) {
    }

    public function index(): JsonResponse
    {
        $users = $this->adminUserService->list();

        return response()->json([
            'status' => 'success',
            'data'   => $users,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $user = $this->adminUserService->get($id);

        return response()->json([
            'status' => 'success',
            'data'   => $user,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username'   => 'required|string|max:100',
            'email'      => 'required|email|max:200',
            'password'   => 'required|string|min:8',
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'mobile'     => 'nullable|string|max:20',
        ]);

        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;

        $user = $this->adminUserService->create(
            $validated['username'],
            $validated['email'],
            $validated['password'],
            $validated['first_name'] ?? null,
            $validated['last_name'] ?? null,
            $validated['mobile'] ?? null,
            $actorId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Admin user created successfully.',
            'data'    => $user,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'mobile'     => 'nullable|string|max:20',
            'status'     => 'nullable|integer|in:0,1',
        ]);

        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;

        $user = $this->adminUserService->update(
            $id,
            $validated['first_name'] ?? null,
            $validated['last_name'] ?? null,
            $validated['mobile'] ?? null,
            $validated['status'] ?? null,
            $actorId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Admin user updated successfully.',
            'data'    => $user,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;
        $this->adminUserService->softDelete($id, $actorId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Admin user deleted successfully.',
        ]);
    }
}
