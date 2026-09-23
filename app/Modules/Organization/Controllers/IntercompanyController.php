<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\IntercompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntercompanyController extends Controller
{
    public function __construct(
        private readonly IntercompanyService $service
    ) {
    }

    public function partners(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listPartners(),
        ]);
    }

    public function storePartner(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_company_id'     => 'required|uuid',
            'to_company_id'       => 'required|uuid',
            'partner_customer_id' => 'nullable|uuid',
            'partner_vendor_id'   => 'nullable|uuid',
            'notes'               => 'nullable|string|max:500',
        ]);

        $row = $this->service->mapPartners(
            $data['from_company_id'],
            $data['to_company_id'],
            $data['partner_customer_id'] ?? null,
            $data['partner_vendor_id'] ?? null,
            $data['notes'] ?? null,
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'IC partner mapped.',
            'data'    => $row,
        ], 201);
    }

    public function rules(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listRules(),
        ]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'               => 'required|string|max:50',
            'name'               => 'required|string|max:200',
            'source_doc_type'    => 'required|string|max:50',
            'target_doc_type'    => 'required|string|max:50',
            'auto_create_mirror' => 'sometimes|boolean',
        ]);

        $row = $this->service->createRule(
            $data['code'],
            $data['name'],
            $data['source_doc_type'],
            $data['target_doc_type'],
            (bool) ($data['auto_create_mirror'] ?? true),
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'IC rule created.',
            'data'    => $row,
        ], 201);
    }
}
