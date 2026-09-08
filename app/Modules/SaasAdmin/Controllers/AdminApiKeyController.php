<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\AdminApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminApiKeyController extends Controller
{
    public function __construct(
        private readonly AdminApiKeyService $adminApiKeyService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $adminUserId = $request->query('admin_user_id') ?? $request->user()?->admin_user_id;
        if (!$adminUserId) {
            return response()->json(['status' => 'error', 'message' => 'admin_user_id required.'], 422);
        }

        $list = $this->adminApiKeyService->listForAdmin($adminUserId);

        return response()->json(['status' => 'success', 'data' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'admin_user_id' => 'required|uuid',
            'name'          => 'required|string|max:100',
            'expires_at'    => 'nullable|date',
        ]);

        $result = $this->adminApiKeyService->create(
            $validated['admin_user_id'],
            $validated['name'],
            isset($validated['expires_at']) ? new \DateTimeImmutable($validated['expires_at']) : null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'API key created. Store plain_key securely; it will not be shown again.',
            'data'    => [
                'api_key'   => $result['model'],
                'plain_key' => $result['plain_key'],
            ],
        ], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->adminApiKeyService->revoke($id);

        return response()->json(['status' => 'success', 'message' => 'API key revoked.']);
    }
}
