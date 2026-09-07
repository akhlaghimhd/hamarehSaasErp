<?php

namespace App\Modules\HrManagement\Controllers;

use App\Base\Controller;
use App\Modules\HrManagement\Services\HrDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrDocumentController extends Controller
{
    public function __construct(
        private readonly HrDocumentService $service
    ) {
    }

    public function index(string $employeeId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->listForEmployee($employeeId),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'        => ['required', 'uuid'],
            'document_type_code' => ['required', 'string', 'max:100'],
            'document_title'     => ['required', 'string', 'max:200'],
            'issue_date'         => ['nullable', 'date'],
            'expiry_date'        => ['nullable', 'date'],
            'attachment_id'      => ['nullable', 'uuid'],
        ]);

        $doc = $this->service->create($data);

        return response()->json(['success' => true, 'data' => $doc], 201);
    }
}
