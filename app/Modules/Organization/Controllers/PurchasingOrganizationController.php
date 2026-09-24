<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\PurchasingOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasingOrganizationController extends Controller
{
    public function __construct(
        private readonly PurchasingOrganizationService $service
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listForTenant(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'       => 'required|string|max:50',
            'name'       => 'required|string|max:200',
            'company_id' => 'nullable|uuid',
        ]);

        $row = $this->service->create($data['code'], $data['name'], $data['company_id'] ?? null);

        return response()->json([
            'status'  => 'success',
            'message' => 'Purchasing organization created.',
            'data'    => $row,
        ], 201);
    }

    public function assign(string $purchOrg, Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|uuid',
            'branch_id'  => 'nullable|uuid',
        ]);

        $row = $this->service->assign(
            $purchOrg,
            $data['company_id'] ?? null,
            $data['branch_id'] ?? null,
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Assignment created.',
            'data'    => $row,
        ], 201);
    }
}
