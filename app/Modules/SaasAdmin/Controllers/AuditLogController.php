<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id'   => 'nullable|uuid',
            'entity_name' => 'nullable|string|max:100',
            'action_type' => 'nullable|string|max:50',
            'limit'       => 'nullable|integer|min:1|max:500',
        ]);

        $list = $this->auditLogService->list(
            $validated['tenant_id'] ?? null,
            $validated['entity_name'] ?? null,
            $validated['action_type'] ?? null,
            $validated['limit'] ?? 100
        );

        return response()->json([
            'status' => 'success',
            'data'   => $list,
        ]);
    }
}
