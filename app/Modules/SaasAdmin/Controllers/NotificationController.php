<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID') ?? app('current_tenant_id') ?? null;
        $userId = $request->user()?->user_id ?? $request->query('recipient_user_id');

        if (!$tenantId || !$userId) {
            return response()->json(['status' => 'error', 'message' => 'tenant_id and recipient required.'], 422);
        }

        $unreadOnly = $request->boolean('unread_only', false);
        $list = $this->notificationService->listForRecipient($tenantId, $userId, $unreadOnly);

        return response()->json(['status' => 'success', 'data' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id'         => 'required|uuid',
            'recipient_user_id' => 'required|uuid',
            'title'             => 'required|string|max:200',
            'body'              => 'required|string',
            'type_code'         => 'required|string|max:50',
        ]);

        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;

        $n = $this->notificationService->create(
            $validated['tenant_id'],
            $validated['recipient_user_id'],
            $validated['title'],
            $validated['body'],
            $validated['type_code'],
            $actorId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification created.',
            'data'    => $n,
        ], 201);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;
        $n = $this->notificationService->markRead($id, $actorId);

        return response()->json(['status' => 'success', 'data' => $n]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;
        $this->notificationService->softDelete($id, $actorId);

        return response()->json(['status' => 'success', 'message' => 'Notification deleted.']);
    }
}
