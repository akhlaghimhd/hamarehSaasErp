<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\CostCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $data = $this->validated($request);
        $data['company_id'] = $company;
        $row = $this->service->create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cost center created.',
            'data'    => $row,
        ], 201);
    }

    public function update(string $costCenter, Request $request): JsonResponse
    {
        $data = $this->validated($request, updating: true);
        $row = $this->service->update($costCenter, $data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cost center updated.',
            'data'    => $row,
        ]);
    }

    public function destroy(string $costCenter): JsonResponse
    {
        $this->service->softDelete($costCenter);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cost center deleted.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'code'                  => [$updating ? 'sometimes' : 'required', 'string', 'max:50'],
            'name'                  => [$updating ? 'sometimes' : 'required', 'string', 'max:200'],
            'cost_center_type'      => ['sometimes', 'string', 'max:30', Rule::in(CostCenterService::TYPES)],
            'department_id'         => 'nullable|uuid',
            'parent_cost_center_id' => 'nullable|uuid',
            'manager_user_id'       => 'nullable|uuid',
            'description'           => 'nullable|string|max:500',
            'valid_from'            => 'nullable|date',
            'valid_to'              => 'nullable|date|after_or_equal:valid_from',
            'is_active'             => 'sometimes|boolean',
        ]);
    }
}
