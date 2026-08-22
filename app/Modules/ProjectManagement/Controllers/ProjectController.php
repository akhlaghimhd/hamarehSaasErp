<?php

namespace App\Modules\ProjectManagement\Controllers;

use App\Base\Controller; // کنترلر پایه‌ای که در مستندات ذکر کرده‌اید
use App\Modules\ProjectManagement\DTOs\Project\CreateProjectDTO;
use App\Modules\ProjectManagement\Requests\Project\CreateProjectRequest;
use App\Modules\ProjectManagement\Services\ProjectService;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projectService)
    {
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        $dto = CreateProjectDTO::fromRequest($request);
        
        $project = $this->projectService->createProject($dto);

        return response()->json([
            'message' => 'Project created successfully and event published.',
            'data'    => $project
        ], 201);
    }
}