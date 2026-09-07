<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Services\BomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BomController extends Controller
{
    public function __construct(
        private readonly BomService $service
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->list(),
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
            'item_id'                      => ['required', 'uuid'],
            'version_code'                 => ['required', 'string', 'max:50'],
            'title'                        => ['required', 'string', 'max:200'],
            'is_active'                    => ['nullable', 'boolean'],
            'status'                       => ['nullable', 'integer', 'in:1,2,3'],
            'items'                        => ['nullable', 'array'],
            'items.*.material_item_id'     => ['required_with:items', 'uuid'],
            'items.*.quantity'             => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.scrap_percentage'     => ['nullable', 'numeric', 'min:0'],
            'items.*.notes'                => ['nullable', 'string'],
        ]);

        $bom = $this->service->create($data);

        return response()->json([
            'success' => true,
            'data'    => $bom,
        ], 201);
    }

    public function approve(string $id): JsonResponse
    {
        $bom = $this->service->approve($id);

        return response()->json([
            'success' => true,
            'data'    => $bom,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true]);
    }
}
