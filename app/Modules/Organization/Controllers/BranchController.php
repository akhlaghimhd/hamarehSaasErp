<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateBranchRequest;
use App\Modules\Organization\Services\BranchService;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    public function __construct(private readonly BranchService $branchService) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->branchService->getAllBranches()]);
    }

    public function store(CreateBranchRequest $request): JsonResponse
    {
        try {
            $branch = $this->branchService->createBranch($request->toDTO());
            return response()->json(['message' => 'شعبه با موفقیت ثبت شد.', 'data' => $branch], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
    public function update(\App\Modules\Organization\Requests\UpdateBranchRequest $request, string $id): JsonResponse
    {
        try {
            $branch = $this->branchService->updateBranch($id, $request->toDTO());
            return response()->json(['message' => 'اطلاعات شعبه با موفقیت ویرایش شد.', 'data' => $branch], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->branchService->deleteBranch($id);
            return response()->json(['message' => 'شعبه با موفقیت حذف شد.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}