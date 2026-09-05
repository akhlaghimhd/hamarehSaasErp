<?php

namespace App\Modules\Inventory\Controllers;

use App\Base\Controller;
use App\Modules\Inventory\Services\StockReservationService;
use App\Modules\Inventory\Requests\ReserveStockRequest;
use App\Modules\Inventory\Requests\ReleaseStockReservationRequest;
use Illuminate\Http\JsonResponse;

class StockReservationController extends Controller
{
    public function __construct(
        protected StockReservationService $reservationService
    ) {
    }

    public function reserve(ReserveStockRequest $request): JsonResponse
    {
        $data = $request->validated();

        $balance = $this->reservationService->reserve(
            $data['location_id'],
            $data['item_id'],
            (float) $data['quantity'],
            [
                'source_document_type' => $data['source_document_type'] ?? null,
                'source_document_id'   => $data['source_document_id'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $balance,
        ], 200);
    }

    public function release(ReleaseStockReservationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $balance = $this->reservationService->release(
            $data['location_id'],
            $data['item_id'],
            (float) $data['quantity'],
            [
                'source_document_type' => $data['source_document_type'] ?? null,
                'source_document_id'   => $data['source_document_id'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $balance,
        ], 200);
    }
}
