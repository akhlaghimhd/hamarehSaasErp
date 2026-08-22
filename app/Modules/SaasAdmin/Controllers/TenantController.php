<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreateTenantRequest;
use App\Modules\SaasAdmin\DTOs\CreateTenantDTO;
use App\Modules\SaasAdmin\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService
    ) {
    }

    /**
     * ایجاد یک شرکت جدید
     */
    public function store(CreateTenantRequest $request): JsonResponse
    {
        // دریافت شناسه کاربر از توکن JWT
        $userId = Auth::guard('api')->id();

        // تبدیل ریکوئست به DTO
        $dto = CreateTenantDTO::fromRequest($request->validated());

        // ارسال به سرویس بیزینسی
        $tenant = $this->tenantService->createTenant($dto, $userId);

        return response()->json([
            'message' => 'شرکت جدید با موفقیت ایجاد شد و شما به عنوان مالک آن ثبت شدید.',
            'data'    => $tenant
        ], 201);
    }
}