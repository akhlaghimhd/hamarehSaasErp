<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerDocumentRequest;
use App\Modules\PartnerLayer\Requests\UpdatePartnerDocumentRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerDocumentDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerDocumentDTO;
use App\Modules\PartnerLayer\Services\PartnerDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PartnerDocumentController extends Controller
{
    public function __construct(private readonly PartnerDocumentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->query('partner_id');
        $items = $this->service->getDocuments($partnerId ? (string) $partnerId : null);

        return response()->json(['status' => 'success', 'data' => $items]);
    }

    public function store(CreatePartnerDocumentRequest $request): JsonResponse
    {
        try {
            $item = $this->service->createDocument(
                CreatePartnerDocumentDTO::fromRequest($request->validated())
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Document created successfully.',
                'data' => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function show(string $document): JsonResponse
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => $this->service->getDocumentById($document),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Document not found or access denied.'], 404);
        }
    }

    public function update(string $document, UpdatePartnerDocumentRequest $request): JsonResponse
    {
        try {
            $item = $this->service->updateDocument(
                $document,
                UpdatePartnerDocumentDTO::fromRequest($request->validated())
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Document updated successfully.',
                'data' => $item,
            ]);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $status);
        }
    }

    public function destroy(string $document): JsonResponse
    {
        try {
            $this->service->deleteDocument($document);

            return response()->json(['status' => 'success', 'message' => 'Document deleted successfully.']);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $status);
        }
    }
}
