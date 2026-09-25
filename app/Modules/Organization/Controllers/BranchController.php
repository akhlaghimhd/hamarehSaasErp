<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateBranchRequest;
use App\Modules\Organization\Requests\UpdateBranchRequest;
use App\Modules\Organization\DTOs\CreateBranchDTO;
use App\Modules\Organization\DTOs\UpdateBranchDTO;
use App\Modules\Organization\Services\BranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(
        private readonly BranchService $branchService
    ) {
    }

    /**
     * List branches with Scope + optional company filter from nested route.
     * GET /api/organization/companies/{company}/branches?membership=active|deleted
     */
    public function index(Request $request, ?string $company = null): JsonResponse
    {
        $companyId = $company ?? $request->route('company');
        $membership = strtolower((string) $request->query('membership', 'active'));
        $onlyTrashed = in_array($membership, ['deleted', 'trashed'], true);

        $branches = $this->branchService->getAllBranches(
            companyId: $companyId ? (string) $companyId : null,
            onlyTrashed: $onlyTrashed
        );

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

    public function restore(string $branchId): JsonResponse
    {
        $branch = $this->branchService->restoreBranch($branchId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Branch restored successfully.',
            'data'    => $branch,
        ]);
    }
}
