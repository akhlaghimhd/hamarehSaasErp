<?php

namespace App\Modules\ProjectManagement\Controllers;

use App\Base\Controller;
use App\Modules\ProjectManagement\DTOs\ProjectTask\CreateProjectTaskDTO;
use App\Modules\ProjectManagement\Requests\ProjectTask\CreateProjectTaskRequest;
use App\Modules\ProjectManagement\Services\ProjectTaskService;
use Illuminate\Http\JsonResponse;

class ProjectTaskController extends Controller
{
    public function __construct(private readonly ProjectTaskService $projectTaskService)
    {
    }

    public function store(CreateProjectTaskRequest $request): JsonResponse
    {
        $dto = CreateProjectTaskDTO::fromRequest($request);
        
        $task = $this->projectTaskService->createTask($dto);

        return response()->json([
            'message' => 'Project task created successfully and event published.',
            'data'    => $task
        ], 201);
    }
}