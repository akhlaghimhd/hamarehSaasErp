<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\CompanyOfficerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyOfficerController extends Controller
{
    public function __construct(
        private readonly CompanyOfficerService $service
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
            'message' => 'Officer created.',
            'data'    => $row,
        ], 201);
    }

    public function update(string $officer, Request $request): JsonResponse
    {
        $data = $this->validated($request, updating: true);
        $row = $this->service->update($officer, $data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Officer updated.',
            'data'    => $row,
        ]);
    }

    public function destroy(string $officer): JsonResponse
    {
        $this->service->softDelete($officer);

        return response()->json([
            'status'  => 'success',
            'message' => 'Officer deleted.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $roleRule = Rule::in(CompanyOfficerService::LEGAL_ROLE_CODES);

        return $request->validate([
            'role_code'             => [$updating ? 'sometimes' : 'required', 'string', 'max:50', $roleRule],
            'full_name'             => [$updating ? 'sometimes' : 'required', 'string', 'max:200'],
            'role_title'            => 'nullable|string|max:150',
            'person_user_id'        => 'nullable|uuid',
            'ownership_id'          => 'nullable|uuid',
            'national_id'           => 'nullable|string|max:20',
            'mandate_from'          => 'nullable|date',
            'mandate_to'            => 'nullable|date|after_or_equal:mandate_from',
            'has_signing_authority' => 'sometimes|boolean',
            'mandate_notes'         => 'nullable|string|max:500',
            'is_active'             => 'sometimes|boolean',
        ]);
    }
}
