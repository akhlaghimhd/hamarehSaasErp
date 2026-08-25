<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreateTenantRequest;
use App\Modules\SaasAdmin\DTOs\CreateTenantDTO;
use App\Modules\SaasAdmin\Services\TenantService;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService
    ) {
    }

    /**
     * Create a new tenant (company).
     * Permission: saas-admin.tenant.create
     */
    public function store(CreateTenantRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $userId = $user->user_id;

        $dto = CreateTenantDTO::fromRequest($request->validated());

        $tenant = $this->tenantService->createTenant($dto, $userId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tenant created successfully and you were assigned as owner.',
            'data'    => [
                'tenant_id'   => $tenant->tenant_id,
                'tenant_code' => $tenant->tenant_code,
                'tenant_name' => $tenant->tenant_name,
                'slug'        => $tenant->slug,
                'status'      => $tenant->status,
            ],
        ], 201);
    }
}