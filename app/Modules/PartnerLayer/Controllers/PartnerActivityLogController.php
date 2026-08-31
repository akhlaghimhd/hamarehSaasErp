<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerActivityLogRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerActivityLogDTO;
use App\Modules\PartnerLayer\Services\PartnerActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

/** Append-only: index, store, show only. */
class PartnerActivityLogController extends Controller
{
    public function __construct(private readonly PartnerActivityLogService $service) {}

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->query('partner_id');
        $items = $this->service->getLogs($partnerId ? (string) $partnerId : null);

        return response()->json(['status' => 'success', 'data' => $items]);
    }

    public function store(CreatePartnerActivityLogRequest $request): JsonResponse
    {
        try {
            $item = $this->service->createLog(
                CreatePartnerActivityLogDTO::fromRequest($request->validated())
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Activity log created successfully.',
                'data' => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function show(string $activityLog): JsonResponse
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => $this->service->getLogById($activityLog),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Activity log not found or access denied.'], 404);
        }
    }
}
