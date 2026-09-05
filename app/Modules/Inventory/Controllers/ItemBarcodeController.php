<?php

namespace App\Modules\Inventory\Controllers;

use App\Base\Controller;
use App\Modules\Inventory\Services\ItemBarcodeService;
use App\Modules\Inventory\Requests\CreateItemBarcodeRequest;
use App\Modules\Inventory\Requests\UpdateItemBarcodeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemBarcodeController extends Controller
{
    public function __construct(
        protected ItemBarcodeService $barcodeService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $rows = $this->barcodeService->getAll($request->query('item_id'));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->barcodeService->getById($id),
        ]);
    }

    public function store(CreateItemBarcodeRequest $request): JsonResponse
    {
        $row = $this->barcodeService->create($request->validated());

        return response()->json(['success' => true, 'data' => $row], 201);
    }

    public function update(UpdateItemBarcodeRequest $request, string $id): JsonResponse
    {
        $row = $this->barcodeService->update($id, $request->validated());

        return response()->json(['success' => true, 'data' => $row]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->barcodeService->delete($id);

        return response()->json(['success' => true]);
    }
}
