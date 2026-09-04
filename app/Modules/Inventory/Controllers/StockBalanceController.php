<?php

namespace App\Modules\Inventory\Controllers;

use App\Base\Controller;
use App\Modules\Inventory\Services\StockBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockBalanceController extends Controller
{
    public function __construct(
        protected StockBalanceService $stockBalanceService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'warehouse_id' => $request->query('warehouse_id'),
            'location_id'  => $request->query('location_id'),
            'item_id'      => $request->query('item_id'),
        ]);

        $balances = $this->stockBalanceService->getAllBalances($filters);

        return response()->json([
            'success' => true,
            'data'    => $balances,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $balance = $this->stockBalanceService->getBalanceById($id);

        return response()->json([
            'success' => true,
            'data'    => $balance,
        ]);
    }
}
