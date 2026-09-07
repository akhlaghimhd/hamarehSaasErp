<?php

namespace App\Modules\ProjectManagement\Controllers;

use App\Base\Controller;
use App\Modules\ProjectManagement\Services\ProjectMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
    public function __construct(private readonly ProjectMemberService $service)
    {
    }

    public function index(string $projectId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->listForProject($projectId)]);
    }

    public function store(Request $request, string $projectId): JsonResponse
    {
        $data = $request->validate([
            'employee_id'  => ['required', 'uuid'],
            'project_role' => ['required', 'string', 'max:100'],
            'joined_at'    => ['nullable', 'date'],
        ]);

        return response()->json(['success' => true, 'data' => $this->service->add($projectId, $data)], 201);
    }

    public function remove(string $memberId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->remove($memberId)]);
    }
}
