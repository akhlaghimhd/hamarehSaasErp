<?php

namespace App\Modules\Accounting\Controllers;

use App\Base\Controller;
use App\Modules\Accounting\Requests\StoreFinancialVoucherItemRequest;
use App\Modules\Accounting\Services\FinancialVoucherItemService;
use Illuminate\Http\JsonResponse;

class FinancialVoucherItemController extends Controller
{
    public function store(StoreFinancialVoucherItemRequest $request, FinancialVoucherItemService $service): JsonResponse
    {
        $dto = $request->toDTO();
        $item = $service->addItem($dto);

        return response()->json([
            'success' => true,
            'message' => 'Voucher item added successfully.',
            'data' => [
                'item_id' => $item->item_id,
                'voucher_id' => $item->voucher_id,
            ]
        ], 201);
    }
}