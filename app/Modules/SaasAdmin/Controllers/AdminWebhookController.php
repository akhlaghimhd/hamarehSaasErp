<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\AdminWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWebhookController extends Controller
{
    public function __construct(
        private readonly AdminWebhookService $adminWebhookService
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->adminWebhookService->list(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:150',
            'target_url'   => 'required|url|max:1000',
            'event_types'  => 'required|array|min:1',
            'event_types.*'=> 'string|max:100',
            'secret_token' => 'nullable|string|max:256',
        ]);

        $wh = $this->adminWebhookService->create(
            $validated['name'],
            $validated['target_url'],
            $validated['event_types'],
            $validated['secret_token'] ?? null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Webhook created.',
            'data'    => $wh,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'nullable|string|max:150',
            'target_url'  => 'nullable|url|max:1000',
            'event_types' => 'nullable|array',
            'is_active'   => 'nullable|boolean',
        ]);

        $wh = $this->adminWebhookService->update(
            $id,
            $validated['name'] ?? null,
            $validated['target_url'] ?? null,
            $validated['event_types'] ?? null,
            $validated['is_active'] ?? null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Webhook updated.',
            'data'    => $wh,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->adminWebhookService->deactivate($id);

        return response()->json(['status' => 'success', 'message' => 'Webhook deactivated.']);
    }
}
