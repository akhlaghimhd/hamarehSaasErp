<?php

namespace App\Modules\ProjectManagement\Controllers;

use App\Base\Controller;
use App\Modules\ProjectManagement\DTOs\ProjectMember\AddProjectMemberDTO;
use App\Modules\ProjectManagement\Requests\ProjectMember\AddProjectMemberRequest;
use App\Modules\ProjectManagement\Services\ProjectMemberService;
use Illuminate\Http\JsonResponse;

class ProjectMemberController extends Controller
{
    public function __construct(private readonly ProjectMemberService $projectMemberService)
    {
    }

    public function store(AddProjectMemberRequest $request): JsonResponse
    {
        $dto = AddProjectMemberDTO::fromRequest($request);
        
        $member = $this->projectMemberService->addMember($dto);

        return response()->json([
            'message' => 'Project member added successfully and event published.',
            'data'    => $member
        ], 201);
    }
}