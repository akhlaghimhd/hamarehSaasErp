<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\CompanyFiscalAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyFiscalAssignmentController extends Controller
{
    public function __construct(
        private readonly CompanyFiscalAssignmentService $service
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
            'period_id'  => 'required|uuid',
            'is_primary' => 'sometimes|boolean',
        ]);

        $row = $this->service->assign(
            $company,
            $data['period_id'],
            (bool) ($data['is_primary'] ?? false),
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Fiscal assignment created.',
            'data'    => $row,
        ], 201);
    }

    public function destroy(string $assignment): JsonResponse
    {
        $this->service->softDelete($assignment);

        return response()->json([
            'status'  => 'success',
            'message' => 'Fiscal assignment deleted.',
        ]);
    }
}
