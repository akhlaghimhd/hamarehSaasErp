<?php

namespace App\Modules\ProjectManagement\Controllers;

use App\Base\Controller;
use App\Modules\ProjectManagement\Services\ProjectTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTaskController extends Controller
{
    public function __construct(private readonly ProjectTaskService $service)
    {
    }

    public function index(string $projectId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->listForProject($projectId)]);
    }

    public function store(Request $request, string $projectId): JsonResponse
    {
        $data = $request->validate([
            'task_code'       => ['required', 'string', 'max:50'],
            'title'           => ['required', 'string', 'max:200'],
            'description'     => ['nullable', 'string'],
            'parent_task_id'  => ['nullable', 'uuid'],
            'priority'        => ['nullable', 'integer', 'in:1,2,3,4'],
            'start_date'      => ['nullable', 'date'],
            'due_date'        => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(['success' => true, 'data' => $this->service->create($projectId, $data)], 201);
    }

    public function start(string $taskId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->start($taskId)]);
    }

    public function complete(Request $request, string $taskId): JsonResponse
    {
        $data = $request->validate([
            'actual_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->service->complete($taskId, (float) ($data['actual_hours'] ?? 0)),
        ]);
    }
}
