<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportTicketService $supportTicketService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID') ?? $request->query('tenant_id');
        if (!$tenantId) {
            return response()->json(['status' => 'error', 'message' => 'tenant_id required.'], 422);
        }

        $list = $this->supportTicketService->listByTenant($tenantId);

        return response()->json(['status' => 'success', 'data' => $list]);
    }

    public function show(string $id): JsonResponse
    {
        $ticket = $this->supportTicketService->get($id);

        return response()->json(['status' => 'success', 'data' => $ticket]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id'      => 'required|uuid',
            'subject'        => 'required|string|max:300',
            'description'    => 'nullable|string',
            'tenant_user_id' => 'nullable|uuid',
            'priority'       => 'nullable|integer|min:1|max:5',
            'channel'        => 'nullable|string|max:50',
        ]);

        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;

        $ticket = $this->supportTicketService->create(
            $validated['tenant_id'],
            $validated['subject'],
            $validated['description'] ?? null,
            $validated['tenant_user_id'] ?? null,
            $validated['priority'] ?? 2,
            $validated['channel'] ?? 'PORTAL',
            $actorId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Support ticket created.',
            'data'    => $ticket,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'subject'                => 'nullable|string|max:300',
            'description'            => 'nullable|string',
            'priority'               => 'nullable|integer|min:1|max:5',
            'status'                 => 'nullable|integer',
            'assigned_admin_user_id' => 'nullable|uuid',
        ]);

        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;

        $ticket = $this->supportTicketService->update(
            $id,
            $validated['subject'] ?? null,
            $validated['description'] ?? null,
            $validated['priority'] ?? null,
            $validated['status'] ?? null,
            $validated['assigned_admin_user_id'] ?? null,
            $actorId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Support ticket updated.',
            'data'    => $ticket,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;
        $this->supportTicketService->softDelete($id, $actorId);

        return response()->json(['status' => 'success', 'message' => 'Support ticket deleted.']);
    }
}
