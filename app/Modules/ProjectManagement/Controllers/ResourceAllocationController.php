<?php

namespace App\Modules\ProjectManagement\Controllers;

use App\Base\Controller;
use App\Modules\ProjectManagement\Services\ResourceAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceAllocationController extends Controller
{
    public function __construct(
        private readonly ResourceAllocationService $service
    ) {
    }

    public function index(string $taskId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->listForTask($taskId),
        ]);
    }

    public function store(Request $request, string $taskId): JsonResponse
    {
        $data = $request->validate([
            'resource_type'      => ['required', 'integer', 'in:1,2,3'],
            'resource_id'        => ['required', 'uuid'],
            'allocated_quantity' => ['nullable', 'numeric', 'gt:0'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $row = $this->service->allocate($taskId, $data);

        return response()->json(['success' => true, 'data' => $row], 201);
    }

    public function destroy(string $allocationId): JsonResponse
    {
        $this->service->release($allocationId);

        return response()->json(['success' => true]);
    }
}
