<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateBranchRequest;
use App\Modules\Organization\Requests\UpdateBranchRequest;
use App\Modules\Organization\DTOs\CreateBranchDTO;
use App\Modules\Organization\DTOs\UpdateBranchDTO;
use App\Modules\Organization\Services\BranchService;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    public function __construct(
        private readonly BranchService $branchService
    ) {
    }

    public function index(): JsonResponse
    {
        $branches = $this->branchService->getAllBranches();

        return response()->json([
            'status' => 'success',
            'data'   => $branches,
        ]);
    }

    public function store(CreateBranchRequest $request): JsonResponse
    {
        $dto = CreateBranchDTO::fromRequest($request->validated());
        $branch = $this->branchService->createBranch($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Branch created successfully.',
            'data'    => $branch,
        ], 201);
    }

    public function show(string $branchId): JsonResponse
    {
        $branches = $this->branchService->getAllBranches();
        $branch = $branches->firstWhere('branch_id', $branchId);

        if (!$branch) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Branch not found or access denied.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $branch,
        ]);
    }

    public function update(string $branchId, UpdateBranchRequest $request): JsonResponse
    {
        $dto = UpdateBranchDTO::fromRequest($request->validated());
        $branch = $this->branchService->updateBranch($branchId, $dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Branch updated successfully.',
            'data'    => $branch,
        ]);
    }

    public function destroy(string $branchId): JsonResponse
    {
        $this->branchService->deleteBranch($branchId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Branch deleted successfully.',
        ]);
    }
}