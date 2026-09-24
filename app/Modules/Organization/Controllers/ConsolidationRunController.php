<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Models\ConsolidationRun;
use App\Modules\Organization\Services\ConsolidationRunService;
use App\Base\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsolidationRunController extends Controller
{
    public function __construct(
        private readonly ConsolidationRunService $service
    ) {
    }

    public function index(): JsonResponse
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $rows = ConsolidationRun::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'          => 'required|string|max:50',
            'name'          => 'required|string|max:200',
            'hierarchy_id'  => 'nullable|uuid',
            'period_start'  => 'nullable|date',
            'period_end'    => 'nullable|date',
            'rate_set_ref'  => 'nullable|string|max:100',
        ]);

        $row = $this->service->createDraft(
            $data['code'],
            $data['name'],
            $data['hierarchy_id'] ?? null,
            $data['period_start'] ?? null,
            $data['period_end'] ?? null,
            $data['rate_set_ref'] ?? null,
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Consolidation run created.',
            'data'    => $row,
        ], 201);
    }

    public function snapshot(string $consolRun): JsonResponse
    {
        $row = $this->service->snapshot($consolRun);

        return response()->json([
            'status'  => 'success',
            'message' => 'Snapshot captured.',
            'data'    => $row,
        ]);
    }
}
