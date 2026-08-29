<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\CreateScopeRequest;
use App\Modules\IdentityCore\Requests\UpdateScopeRequest;
use App\Modules\IdentityCore\Requests\AssignScopeRequest;
use App\Modules\IdentityCore\DTOs\CreateScopeDTO;
use App\Modules\IdentityCore\DTOs\UpdateScopeDTO;
use App\Modules\IdentityCore\DTOs\AssignScopeToUserDTO;
use App\Modules\IdentityCore\Services\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

class ScopeController extends Controller
{
    public function __construct(
        private readonly ScopeService $scopeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $scopes = $this->scopeService->listScopes($request->query('scope_type'));

            return response()->json([
                'status'  => 'success',
                'message' => 'لیست محدوده‌ها با موفقیت دریافت شد.',
                'data'    => $scopes,
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $scope = $this->scopeService->getScope($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'محدوده با موفقیت دریافت شد.',
                'data'    => $scope,
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function store(CreateScopeRequest $request): JsonResponse
    {
        try {
            $dto = CreateScopeDTO::fromRequest($request->validated());
            $scope = $this->scopeService->createScope($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'محدوده با موفقیت ایجاد شد.',
                'data'    => $scope,
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function update(UpdateScopeRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateScopeDTO::fromRequest($id, $request->validated());
            $scope = $this->scopeService->updateScope($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'محدوده با موفقیت به‌روزرسانی شد.',
                'data'    => $scope,
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->scopeService->deleteScope($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'محدوده با موفقیت حذف شد.',
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function assign(AssignScopeRequest $request): JsonResponse
    {
        try {
            $dto = AssignScopeToUserDTO::fromRequest($request->validated());
            $this->scopeService->assignScopesToUser($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'محدوده‌ها با موفقیت به کاربر تخصیص داده شدند.',
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function unassign(AssignScopeRequest $request): JsonResponse
    {
        try {
            $dto = AssignScopeToUserDTO::fromRequest($request->validated());
            $this->scopeService->unassignScopesFromUser($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'محدوده‌ها با موفقیت از کاربر حذف شدند.',
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function userScopes(string $tenantUserId): JsonResponse
    {
        try {
            $scopes = $this->scopeService->getUserScopes($tenantUserId);

            return response()->json([
                'status'  => 'success',
                'message' => 'محدوده‌های کاربر با موفقیت دریافت شد.',
                'data'    => $scopes,
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    private function errorResponse(Exception $e): JsonResponse
    {
        if ($e instanceof HttpException) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        $status = 400;
        if (str_contains(strtolower($e->getMessage()), 'not found')
            || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            $status = 404;
        }

        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], $status);
    }
}
