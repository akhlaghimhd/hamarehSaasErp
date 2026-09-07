<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Services\QualityInspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QualityInspectionController extends Controller
{
    public function __construct(
        private readonly QualityInspectionService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('qc_status');

        return response()->json([
            'success' => true,
            'data'    => $this->service->list($status !== null ? (int) $status : null),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getById($id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inspection_type'      => ['required', 'integer', 'in:1,2,3'],
            'item_id'              => ['required', 'uuid'],
            'inspection_number'    => ['required', 'string', 'max:100'],
            'sample_quantity'      => ['required', 'numeric', 'gt:0'],
            'source_document_type' => ['nullable', 'string', 'max:100'],
            'source_document_id'   => ['nullable', 'uuid'],
            'batch_id'             => ['nullable', 'uuid'],
            'notes'                => ['nullable', 'string'],
        ]);

        $row = $this->service->create($data);

        return response()->json(['success' => true, 'data' => $row], 201);
    }

    public function dispose(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'qc_status'          => ['required', 'integer', 'in:2,3,4'],
            'accepted_quantity'  => ['required', 'numeric', 'min:0'],
            'rejected_quantity'  => ['required', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string'],
        ]);

        $row = $this->service->dispose(
            $id,
            (int) $data['qc_status'],
            (float) $data['accepted_quantity'],
            (float) $data['rejected_quantity'],
            $data['notes'] ?? null
        );

        return response()->json(['success' => true, 'data' => $row]);
    }
}
