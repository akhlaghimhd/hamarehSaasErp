<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerTenantAssignmentRequest;
use App\Modules\PartnerLayer\Requests\UpdatePartnerTenantAssignmentRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerTenantAssignmentDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerTenantAssignmentDTO;
use App\Modules\PartnerLayer\Services\PartnerTenantAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PartnerTenantAssignmentController extends Controller
{
    public function __construct(
        private readonly PartnerTenantAssignmentService $assignmentService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->query('partner_id');

        $items = $this->assignmentService->getAssignments(
            $partnerId ? (string) $partnerId : null
        );

        return response()->json([
            'status' => 'success',
            'data'   => $items,
        ]);
    }

    public function store(CreatePartnerTenantAssignmentRequest $request): JsonResponse
    {
        try {
            $dto = CreatePartnerTenantAssignmentDTO::fromRequest($request->validated());
            $item = $this->assignmentService->createAssignment($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Partner tenant assignment created successfully.',
                'data'    => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $assignment): JsonResponse
    {
        try {
            $item = $this->assignmentService->getAssignmentById($assignment);

            return response()->json([
                'status' => 'success',
                'data'   => $item,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Assignment not found or access denied.',
            ], 404);
        }
    }

    public function update(string $assignment, UpdatePartnerTenantAssignmentRequest $request): JsonResponse
    {
        try {
            $dto = UpdatePartnerTenantAssignmentDTO::fromRequest($request->validated());
            $item = $this->assignmentService->updateAssignment($assignment, $dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Assignment updated successfully.',
                'data'    => $item,
            ]);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function destroy(string $assignment): JsonResponse
    {
        try {
            $this->assignmentService->deleteAssignment($assignment);

            return response()->json([
                'status'  => 'success',
                'message' => 'Assignment deleted successfully.',
            ]);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
