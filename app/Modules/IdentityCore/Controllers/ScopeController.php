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

class ScopeController extends Controller
{
    public function __construct(
        private readonly ScopeService $scopeService
    ) {}

    /**
     * لیست محدوده‌های مستأجر جاری
     */
    public function index(Request $request): JsonResponse
    {
        $scopes = $this->scopeService->listScopes($request->query('scope_type'));

        return response()->json([
            'status'  => 'success',
            'message' => 'لیست محدوده‌ها با موفقیت دریافت شد.',
            'data'    => $scopes,
        ], 200);
    }

    /**
     * دریافت یک محدوده
     */
    public function show(string $id): JsonResponse
    {
        $scope = $this->scopeService->getScope($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'محدوده با موفقیت دریافت شد.',
            'data'    => $scope,
        ], 200);
    }

    /**
     * ایجاد محدوده جدید
     */
    public function store(CreateScopeRequest $request): JsonResponse
    {
        $dto = CreateScopeDTO::fromRequest($request->validated());
        $scope = $this->scopeService->createScope($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'محدوده با موفقیت ایجاد شد.',
            'data'    => $scope,
        ], 201);
    }

    /**
     * به‌روزرسانی محدوده
     */
    public function update(UpdateScopeRequest $request, string $id): JsonResponse
    {
        $dto = UpdateScopeDTO::fromRequest($id, $request->validated());
        $scope = $this->scopeService->updateScope($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'محدوده با موفقیت به‌روزرسانی شد.',
            'data'    => $scope,
        ], 200);
    }

    /**
     * حذف محدوده
     */
    public function destroy(string $id): JsonResponse
    {
        $this->scopeService->deleteScope($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'محدوده با موفقیت حذف شد.',
        ], 200);
    }

    /**
     * تخصیص محدوده‌ها به کاربر
     */
    public function assign(AssignScopeRequest $request): JsonResponse
    {
        $dto = AssignScopeToUserDTO::fromRequest($request->validated());
        $this->scopeService->assignScopesToUser($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'محدوده‌ها با موفقیت به کاربر تخصیص داده شدند.',
        ], 200);
    }

    /**
     * دریافت محدوده‌های یک کاربر
     */
    public function userScopes(string $tenantUserId): JsonResponse
    {
        $scopes = $this->scopeService->getUserScopes($tenantUserId);

        return response()->json([
            'status'  => 'success',
            'message' => 'محدوده‌های کاربر با موفقیت دریافت شد.',
            'data'    => $scopes,
        ], 200);
    }
}