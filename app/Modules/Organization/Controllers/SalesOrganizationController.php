<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\SalesOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOrganizationController extends Controller
{
    public function __construct(
        private readonly SalesOrganizationService $service
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
            'message' => 'Sales organization created.',
            'data'    => $row,
        ], 201);
    }

    public function assign(string $salesOrg, Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|uuid',
            'branch_id'  => 'nullable|uuid',
        ]);

        $row = $this->service->assign(
            $salesOrg,
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
