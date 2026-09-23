<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\CostCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CostCenterController extends Controller
{
    public function __construct(
        private readonly CostCenterService $service
    ) {
    }

    public function index(string $company): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listForCompany($company),
        ]);
    }

    public function store(string $company, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'                  => 'required|string|max:50',
            'name'                  => 'required|string|max:200',
            'department_id'         => 'nullable|uuid',
            'parent_cost_center_id' => 'nullable|uuid',
            'is_active'             => 'sometimes|boolean',
        ]);

        $data['company_id'] = $company;
        $row = $this->service->create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cost center created.',
            'data'    => $row,
        ], 201);
    }
}
