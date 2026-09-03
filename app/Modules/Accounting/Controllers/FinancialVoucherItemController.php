<?php

namespace App\Modules\Accounting\Controllers;

use App\Base\Controller;
use App\Modules\Accounting\Requests\StoreFinancialVoucherItemRequest;
use App\Modules\Accounting\Requests\UpdateFinancialVoucherItemRequest;
use App\Modules\Accounting\DTOs\UpdateFinancialVoucherItemDTO;
use App\Modules\Accounting\Services\FinancialVoucherItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialVoucherItemController extends Controller
{
    public function __construct(private readonly FinancialVoucherItemService $itemService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $items = $this->itemService->getAll($request->query('voucher_id'));

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $item = $this->itemService->getById($id);

        return response()->json([
            'success' => true,
            'data'    => $item,
        ]);
    }

    public function store(StoreFinancialVoucherItemRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        $item = $this->itemService->addItem($dto);

        return response()->json([
            'success' => true,
            'message' => 'Voucher item added successfully.',
            'data'    => [
                'item_id'     => $item->item_id,
                'voucher_id'  => $item->voucher_id,
                'debit'       => $item->debit,
                'credit'      => $item->credit,
                'row_version' => $item->row_version,
            ],
        ], 201);
    }

    public function update(UpdateFinancialVoucherItemRequest $request, string $id): JsonResponse
    {
        $dto = UpdateFinancialVoucherItemDTO::fromRequest($request);
        $item = $this->itemService->updateItem($id, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Voucher item updated successfully.',
            'data'    => $item,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->itemService->deleteItem($id);

        return response()->json([
            'success' => true,
            'message' => 'Voucher item deleted successfully.',
        ]);
    }
}
